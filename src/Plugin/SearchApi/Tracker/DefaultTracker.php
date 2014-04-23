<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Tracker\DefaultTracker.
 */

namespace Drupal\search_api\Plugin\SearchApi\Tracker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\search_api\Tracker\TrackerPluginBase;

/**
 * Default Search API tracker which implements a FIFO-like processing order.
 *
 * @SearchApiTracker(
 *   id = "default_tracker",
 *   label = @Translation("Default"),
 *   description = @Translation("Index tracker which uses first in/first out for processing pending items.")
 * )
 */
class DefaultTracker extends TrackerPluginBase {

  /**
   * A connection to the Drupal database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Create DefaultTracker object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   An instance of Connection which represents the connection to the
   *   database.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(Connection $connection, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
  }

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   An instance of Connection.
   */
  public function getDatabaseConnection() {
    return $this->connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Database\Connection $connection */
    $connection = $container->get('database');
    // Create the plugin instance.
    return new static($connection, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates a select statement.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   An instance of SelectInterface.
   */
  protected function createSelectStatement() {
    return $this->getDatabaseConnection()->select('search_api_item', 'sai')
            ->condition('index_id', $this->getIndex()->id());
  }

  /**
   * Creates an insert statement.
   *
   * @return \Drupal\Core\Database\Query\Insert
   *   An instance of Insert.
   */
  protected function createInsertStatement() {
    return $this->getDatabaseConnection()->insert('search_api_item')
            ->fields(array('index_id', 'datasource', 'item_id', 'changed'));
  }

  /**
   * Creates an update statement.
   *
   * @return \Drupal\Core\Database\Query\Update
   *   An instance of Update.
   */
  protected function createUpdateStatement() {
    return $this->getDatabaseConnection()->update('search_api_item')
            ->condition('index_id', $this->getIndex()->id());
  }

  /**
   * Creates a delete statement.
   *
   * @return \Drupal\Core\Database\Query\Delete
   *   An instance of ConditionInterface.
   */
  protected function createDeleteStatement() {
    return $this->getDatabaseConnection()->delete('search_api_item')
            ->condition('index_id', $this->getIndex()->id());
  }

  /**
   * Creates a statement which filters on the remaining items.
   *
   * @param string|null $datasource
   *   (optional) If specified, only items of the datasource with that ID are
   *   retrieved.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   An instance of Select.
   */
  protected function createRemainingItemsStatement($datasource = NULL) {
    $select = $this->createSelectStatement();
    $fields = array('item_id');
    if ($datasource) {
      $select->condition('datasource', $datasource);
    }
    else {
      $fields[] = 'datasource';
    }
    $select->fields('sai', $fields);
    $select->condition('sai.changed', 0, '>');
    $select->orderBy('sai.changed', 'ASC');

    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function trackInserted($datasource, array $ids) {
    $transaction = $this->getDatabaseConnection()->startTransaction();
    try {
      $index_id = $this->getIndex()->id();
      // Process the IDs in chunks so we don't create an overly large INSERT
      // statement.
      foreach (array_chunk($ids, 1000) as $ids_chunk) {
        $insert = $this->createInsertStatement();
        foreach ($ids_chunk as $item_id) {
          $insert->values(array(
            'index_id' => $index_id,
            'datasource' => $datasource,
            'item_id' => $item_id,
            'changed' => REQUEST_TIME,
          ));
        }
        $insert->execute();
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Search API', $e);
      $transaction->rollback();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsUpdated($datasource_id = NULL, array $ids = NULL) {
    $transaction = $this->getDatabaseConnection()->startTransaction();
    try {
      // Process the IDs in chunks so we don't create an overly large UPDATE
      // statement.
      $ids_chunks = ($ids !== NULL ? array_chunk($ids, 1000) : array(NULL));
      foreach ($ids_chunks as $ids_chunk) {
        $update = $this->createUpdateStatement();
        $update->fields(array('changed' => REQUEST_TIME));
        if ($datasource_id) {
          $update->condition('datasource', $datasource_id);
          if ($ids_chunk) {
            $update->condition('item_id', $ids_chunk);
          }
        }
        $update->execute();
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Search API', $e);
      $transaction->rollback();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsIndexed($datasource, array $ids) {
    $transaction = $this->getDatabaseConnection()->startTransaction();
    try {
      // Process the IDs in chunks so we don't create an overly large UPDATE
      // statement.
      $ids_chunks = array_chunk($ids, 1000);
      foreach ($ids_chunks as $ids_chunk) {
        $update = $this->createUpdateStatement();
        $update->fields(array('changed' => 0));
        $update->condition('datasource', $datasource);
        $update->condition('item_id', $ids_chunk);
        $update->execute();
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Search API', $e);
      $transaction->rollback();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted($datasource = NULL, array $ids = NULL) {
    $transaction = $this->getDatabaseConnection()->startTransaction();
    try {
      // Process the IDs in chunks so we don't create an overly large DELETE
      // statement.
      $ids_chunks = ($ids !== NULL ? array_chunk($ids, 1000) : array(NULL));
      foreach ($ids_chunks as $ids_chunk) {
        $delete = $this->createDeleteStatement();
        if ($datasource) {
          $delete->condition('datasource', $datasource);
          if ($ids_chunk) {
            $delete->condition('item_id', $ids_chunk);
          }
        }
        $delete->execute();
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Search API', $e);
      $transaction->rollback();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItems($limit = -1, $datasource = NULL) {
    $select = $this->createRemainingItemsStatement($datasource);
    if ($limit >= 0) {
      $select->range(0, $limit);
    }
    if ($datasource) {
      return $select->execute()->fetchCol();
    }
    $items = array();
    foreach ($select->execute() as $row) {
      $items[$row->datasource][] = $row->item_id;
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItemsCount($datasource = NULL) {
    $select = $this->createRemainingItemsStatement();
    if ($datasource) {
      $select->condition('datasource', $datasource);
    }
    return (int) $select->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalItemsCount($datasource = NULL) {
    $select = $this->createSelectStatement();
    if ($datasource) {
      $select->condition('datasource', $datasource);
    }
    return (int) $select->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedItemsCount($datasource = NULL) {
    $select = $this->createSelectStatement();
    $select->condition('sai.changed', 0);
    if ($datasource) {
      $select->condition('datasource', $datasource);
    }
    return (int) $select->countQuery()->execute()->fetchField();
  }

}
