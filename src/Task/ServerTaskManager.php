<?php

/**
 * @file
 * Contains \Drupal\search_api\Task\ServerTaskManager.
 */

namespace Drupal\search_api\Task;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Provides the state system using a key value store.
 */
class ServerTaskManager implements ServerTaskManagerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entity_manager;

  /**
   * Creates a new ServerTaskManager service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  function __construct(Connection $database, EntityManagerInterface $entity_manager) {
    $this->database = $database;
    $this->entity_manager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ServerInterface $server = NULL) {
    $database = $this->database;
    $select = $database->select('search_api_task', 't');
    $select->fields('t')
      // Only retrieve tasks we can handle.
      ->condition('t.type', array('addIndex', 'updateIndex', 'removeIndex', 'deleteItems', 'deleteAllIndexItems'));
    if ($server) {
      $select->condition('t.server_id', $server->id());
    }
    else {
      // By ordering by the server, we can later just load them when we reach them
      // while looping through the tasks. It is very unlikely there will be tasks
      // for more than one or two servers, so a *_load_multiple() probably
      // wouldn't bring any significant advantages, but complicate the code.
      $select->orderBy('t.server_id');
    }
    // Store a count query for later checking whether all tasks were processed
    // successfully.
    $count_query = $select->countQuery();

    // Sometimes the order of tasks might be important, so make sure to order by
    // the task ID (which should be in order of insertion).
    $select->orderBy('t.id');
    $tasks = $select->execute();

    $executed_tasks = array();
    foreach ($tasks as $task) {
      if (!$server || $server->id() != $task->server_id) {
        $server = $this->loadServer($task->server_id);
        if (!$server) {
          continue;
        }
      }
      $index = NULL;
      if ($task->index_id) {
        $index = $this->loadIndex($task->index_id);
      }
      switch ($task->type) {
        case 'addIndex':
          if ($index) {
            $server->addIndex($index);
          }
          break;

        case 'updateIndex':
          if ($index) {
            if ($task->data) {
              $index->original = unserialize($task->data);
            }
            $server->updateIndex($index);
          }
          break;

        case 'removeIndex':
          if ($index) {
            $server->removeIndex($index ? $index : $task->index_id);
          }
          break;

        case 'deleteItems':
          $ids = unserialize($task->data);
          $server->deleteItems($index, $ids);
          break;

        case 'deleteAllIndexItems':
          if ($index) {
            $server->deleteAllIndexItems($index);
          }
          break;

        default:
          // This should never happen.
          continue;
      }
      $executed_tasks[] = $task->id;
    }

    // If there were no tasks (we recognized), return TRUE.
    if (!$executed_tasks) {
      return TRUE;
    }
    // Otherwise, delete the executed tasks and check if new tasks were created.
    $this->delete($executed_tasks);
    return $count_query->execute()->fetchField() === 0;
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
  public function delete(array $ids = NULL, ServerInterface $server = NULL, IndexInterface $index = NULL) {
    $delete = $this->database->delete('search_api_task');
    if ($ids) {
      $delete->condition('id', $ids);
    }
    if ($server) {
      $delete->condition('server_id', $server->id());
    }
    if ($index) {
      $delete->condition('index_id', $index->id());
    }
    $delete->execute();
  }

  /**
   * Loads a search server.
   *
   * @param $server_id
   *   The server's machine name.
   *
   * @return \Drupal\search_api\Server\ServerInterface
   *   The loaded server, or NULL if it could not be loaded.
   */
  protected function loadServer($server_id) {
    return $this->entity_manager->getStorage('search_api_server')->load($server_id);
  }

  /**
   * Loads a search index.
   *
   * @param $index_id
   *   The index's machine name.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The loaded index, or NULL if it could not be loaded.
   */
  protected function loadIndex($index_id) {
    return $this->entity_manager->getStorage('search_api_index')->load($index_id);
  }

}
