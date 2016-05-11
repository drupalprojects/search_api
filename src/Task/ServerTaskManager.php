<?php

namespace Drupal\search_api\Task;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;

/**
 * Provides a service for managing pending server tasks.
 */
class ServerTaskManager implements ServerTaskManagerInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ServerTaskManager object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getCount(ServerInterface $server = NULL) {
    return $this->createQuery($server)
      ->range()
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ServerInterface $server = NULL) {
    if ($server && !$server->status()) {
      return FALSE;
    }

    $select = $this->createQuery($server);

    // Execute the SELECT query and remember the limit used. (Weirdly, the
    // query object doesn't seem to have a method for retrieving the range after
    // setting it.)
    $tasks = $select->execute();
    $fetch_count = $select->getMetaData('search_api_server_tasks_limit');

    // After all possible modifications to the query have been made, we can now
    // also determine the total task count we would have had. Unfortunately,
    // since we had to set the range before (which countQuery() doesn't
    // automatically remove for some reason), this isn't completely
    // straight-forward.
    $count_query = clone $select;
    $total_count = $count_query->range()->countQuery()->execute()->fetchField();

    $executed_tasks = array();
    $failing_servers = array();
    foreach ($tasks as $task) {
      if (isset($failing_servers[$task->server_id])) {
        continue;
      }
      if (!$server || $server->id() != $task->server_id) {
        $server = $this->loadServer($task->server_id);
        if (!$server) {
          $failing_servers[$task->server_id] = TRUE;
          continue;
        }
      }
      if (!$server->status()) {
        continue;
      }
      $index = NULL;
      if ($task->index_id) {
        $index = $this->loadIndex($task->index_id);
      }
      try {
        if ($this->executeTask($task, $server, $index)) {
          $executed_tasks[] = $task->id;
        }
      }
      catch (SearchApiException $e) {
        // If a task fails, we don't want to execute any other tasks for that
        // server (since order might be important).
        watchdog_exception('search_api', $e);
        $failing_servers[$task->server_id] = TRUE;
      }
    }

    // Delete all successfully executed tasks.
    if ($executed_tasks) {
      $this->delete($executed_tasks);
    }
    // Return TRUE if no tasks failed (i.e., if we didn't mark any server as
    // failing) and we retrieved all of them.
    return !$failing_servers && $total_count <= $fetch_count;
  }

  /**
   * Creates a SELECT query for retrieving tasks.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server for which to retrieve tasks, or NULL to retrieve
   *   tasks for all servers.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   A SELECT query for retrieving the tasks.
   */
  protected function createQuery(ServerInterface $server = NULL) {
    $select = $this->database->select('search_api_task', 't');
    $select->fields('t');
    // Only retrieve tasks we can handle.
    $select->condition('t.type', $this->getSupportedTypes(), 'IN');
    if ($server) {
      $select->condition('t.server_id', $server->id());
    }
    else {
      // By ordering by the server, we can later just load them when we reach
      // them while looping through the tasks. It is very unlikely there will be
      // tasks for more than one or two servers, so a *_load_multiple() probably
      // wouldn't bring any significant advantages, but complicate the code.
      $select->orderBy('t.server_id');
    }

    // Sometimes the order of tasks might be important, so make sure to order by
    // the task ID (which should be in order of insertion).
    $select->orderBy('t.id');

    // Limit number of tasks executed in one call of this method, to avoid
    // breaking the site completely if there are too many tasks.
    $select->range(0, 100);

    // Add a tag and metadata in case other modules want to modify this query.
    // The "search_api_server_tasks_limit" metadata is provided for modules that
    // change the limit to update it to the new one, so we know about it.
    $select->addTag('search_api_server_tasks')
      ->addMetaData('search_api_server', $server)
      ->addMetaData('search_api_server_tasks_limit', 100);

    // Make it easier for a subclass to change the query.
    $this->preprocessQuery($select, $server);
    return $select;
  }

  /**
   * Retrieves the task types supported by this task manager.
   *
   * @return string[]
   *   The task types supported by this task manager.
   */
  protected function getSupportedTypes() {
    return array(
      'addIndex',
      'updateIndex',
      'removeIndex',
      'deleteItems',
      'deleteAllIndexItems',
    );
  }

  /**
   * Preprocesses the query used to retrieve tasks.
   *
   * Does nothing by default, but can be overridden by subclasses.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The constructed SELECT query.
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server for which tasks are retrieved, or NULL if tasks
   *   for all servers are retrieved.
   */
  protected function preprocessQuery(SelectInterface $select, ServerInterface $server = NULL) {}

  /**
   * Executes a single server task.
   *
   * @param object $task
   *   A task object, as retrieved from the search_api_task table.
   * @param \Drupal\search_api\ServerInterface $server
   *   The server on which to execute the task.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   (optional) The index to which the task pertains, if any.
   *
   * @return bool
   *   TRUE if the task was successfully executed, FALSE if the task type was
   *   unknown.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If any error occurred while executing the task.
   */
  protected function executeTask($task, ServerInterface $server, IndexInterface $index = NULL) {
    switch ($task->type) {
      case 'addIndex':
        if ($index) {
          $server->getBackend()->addIndex($index);
        }
        return TRUE;

      case 'updateIndex':
        if ($index) {
          if ($task->data) {
            $index->original = unserialize($task->data);
          }
          $server->getBackend()->updateIndex($index);
        }
        return TRUE;

      case 'removeIndex':
        if ($index) {
          $server->getBackend()->removeIndex($index ? $index : $task->index_id);
          $this->delete(NULL, $server, $index);
        }
        return TRUE;

      case 'deleteItems':
        if ($index && !$index->isReadOnly()) {
          $ids = unserialize($task->data);
          $server->getBackend()->deleteItems($index, $ids);
        }
        return TRUE;

      case 'deleteAllIndexItems':
        if ($index && !$index->isReadOnly()) {
          $server->getBackend()->deleteAllIndexItems($index);
        }
        return TRUE;
    }

    // We didn't know that type of task.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecuteBatch(ServerInterface $server = NULL) {
    $batch_definition = array(
      'operations' => array(
        array(array($this, 'processBatch'), array($server ? $server->id() : NULL)),
      ),
      'finished' => array($this, 'finishBatch'),
    );
    // Schedule the batch.
    batch_set($batch_definition);
  }

  /**
   * Executes server tasks as part of a batch operation.
   *
   * @param string|null $server_id
   *   The ID of the server for which tasks should be executed, or NULL to
   *   execute tasks on all servers.
   * @param array $context
   *   The current batch context, as defined in the @link batch Batch operations
   *   @endlink documentation.
   */
  public function processBatch($server_id, array &$context) {
    /** @var \Drupal\search_api\IndexInterface $server */
    $server = $server_id ? $this->loadServer($server_id) : $server_id;

    if (!isset($context['results']['total'])) {
      $context['results']['executed'] = 0;
      $context['results']['total'] = $this->getCount($server);
      if (!$context['results']['total']) {
        $context['finished'] = 1;
        return;
      }
    }

    $finished = $this->execute($server);
    $still_pending = $this->getCount($server);
    $executed = $context['results']['total'] - $still_pending;
    if ($finished  || $executed == $context['results']['executed']) {
      $context['finished'] = 1;
    }
    else {
      $context['finished'] = $still_pending / $context['results']['total'];
      $context['message'] = $this->formatPlural($executed, 'Executed @count task.', 'Executed @count tasks.');
    }
    $context['results']['executed'] = $executed;
  }

  /**
   * Finishes an "execute server tasks" batch.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   */
  public function finishBatch($success, $results) {
    // Check if the batch job was successful.
    if ($success) {
      if ($results['executed']) {
        // Display the number of tasks executed.
        $message = $this->formatPlural($results['executed'], 'Successfully executed @count task.', 'Successfully executed @count tasks.');
        drupal_set_message($message);
      }
      if (!$results['total']) {
        drupal_set_message($this->t('There were no tasks to execute.'), 'warning');
      }
      elseif ($results['executed'] < $results['total']) {
        drupal_set_message($this->t('An error occurred while trying to execute the tasks. Check the logs for details.'), 'error');
      }
    }
    else {
      // Notify the user about the batch job failure.
      drupal_set_message($this->t('An error occurred while trying to execute the tasks. Check the logs for details.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function add(ServerInterface $server, $type, IndexInterface $index = NULL, $data = NULL) {
    $this->database->insert('search_api_task')
      ->fields(array(
        'server_id' => $server->id(),
        'type' => $type,
        'index_id' => $index ? (is_object($index) ? $index->id() : $index) : NULL,
        'data' => isset($data) ? serialize($data) : NULL,
      ))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $ids = NULL, ServerInterface $server = NULL, $index = NULL, array $types = NULL) {
    $delete = $this->database->delete('search_api_task');
    if ($ids) {
      $delete->condition('id', $ids, 'IN');
    }
    if ($server) {
      $delete->condition('server_id', $server->id());
    }
    if ($index) {
      $delete->condition('index_id', $index instanceof IndexInterface ? $index->id() : $index);
    }
    if ($types) {
      $delete->condition('type', $types, 'IN');
    }
    $delete->execute();
  }

  /**
   * Loads a search server.
   *
   * @param string $server_id
   *   The server's ID.
   *
   * @return \Drupal\search_api\ServerInterface|null
   *   The loaded server, or NULL if it could not be loaded.
   */
  protected function loadServer($server_id) {
    return $this->entityTypeManager->getStorage('search_api_server')->load($server_id);
  }

  /**
   * Loads a search index.
   *
   * @param string $index_id
   *   The index's ID.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The loaded index, or NULL if it could not be loaded.
   */
  protected function loadIndex($index_id) {
    return $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
  }

}
