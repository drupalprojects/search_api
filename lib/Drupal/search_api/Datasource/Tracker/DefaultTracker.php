<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Tracker\DefaultTracker.
 */

namespace Drupal\search_api\Datasource\Tracker;

use Exception;
use Drupal\Core\Database\Connection;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\Item\ItemStates;

/**
 * Default datasource tracker which uses the database.
 */
class DefaultTracker implements TrackerInterface {

  /**
   * The index the tracker describes.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  private $index;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $databaseConnection;

  /**
   * The status of this tracker.
   *
   * @var \Drupal\search_api\Datasource\Tracker\TrackerStatusInterface
   */
  private $status;

  /**
   * Create a DefaultTracker object.
   *
   * @param \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection to the database.
   */
  public function __construct(IndexInterface $index, Connection $connection) {
    // Setup object members.
    $this->index = $index;
    $this->databaseConnection = $connection;
    $this->status = new DefaultTrackerStatus($index, $connection);
  }

  /**
   * Get the index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   */
  protected function getIndex() {
    return $this->index;
  }

  /**
   * Get the database connection
   *
   * @return \Drupal\Core\Database\Connection
   *   An instance of Connection.
   */
  protected function getDatabaseConnection() {
    return $this->databaseConnection;
  }

  /**
   * Create a select statement.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   An instance of SelectInterface.
   */
  protected function createSelectStatement() {
    return $this->getDatabaseConnection()->select('search_api_item', 'search_api_item')
     ->condition('index', $this->getIndex()->id());
  }

  /**
   * Create an insert statement.
   *
   * @return \Drupal\Core\Database\Query\Insert
   *   An instance of Insert.
   */
  protected function createInsertStatement() {
    return $this->getDatabaseConnection()->insert('search_api_item')
      ->fields(array('id', 'index', 'state', 'changed'));
  }

  /**
   * Create an update statement.
   *
   * @return \Drupal\Core\Database\Query\Update
   *   An instance of Update.
   */
  protected function createUpdateStatement() {
    return $this->getDatabaseConnection()->update('search_api_item')
      ->condition('index', $this->getIndex()->id());
  }

  /**
   * Create a delete statement.
   *
   * @return \Drupal\Core\Database\Query\Delete
   *   An instance of Delete.
   */
  protected function createDeleteStatement() {
    return $this->getDatabaseConnection()->delete('search_api_item')
      ->condition('index', $this->getIndex()->id());
  }

  /**
   * {@inheritdoc}
   */
  public function trackInsert(array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Get the index ID.
      $index_id = $index->id();
      // Catch any exception that may occur during insert.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build the insert statement.
          $statement = $this->createInsertStatement();
          // Iterate through the chunked IDs.
          foreach ($ids_chunk as $item_id) {
            // Add the ID to the insert statement.
            $statement->values(array(
              'id' => $item_id,
              'index' => $index_id,
              'state' => ItemStates::CHANGED,
              'changed' => REQUEST_TIME,
            ));
          }
          // Execute the insert statement.
          $statement->execute();
        }
        // Indicate successful operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackUpdate(array $ids, $dequeue = FALSE) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build the fields which need to be updated.
          $fields = array(
            'state' => ItemStates::CHANGED,
            'changed' => REQUEST_TIME,
          );
          // Build the update statement.
          $statement = $this->createUpdateStatement()
            ->fields($fields)
            ->condition('id', $ids_chunk);
          // Check if the queued items should not be dequeued.
          if (!$dequeue) {
            // Exclude queued items from the update statement.
            $statement->condition('state', ItemStates::QUEUED, '<>');
          }
          // Execute the update statement.
          $statement->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackQueued(array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build the fields which need to be updated.
          $fields = array(
            'state' => ItemStates::QUEUED,
            'changed' => REQUEST_TIME,
          );
          // Build and execute the update statement.
          $this->createUpdateStatement()
            ->fields($fields)
            ->condition('id', $ids_chunk)
            ->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackIndexed(array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build the fields which need to be updated.
          $fields = array(
            'state' => ItemStates::INDEXED,
            'changed' => REQUEST_TIME,
          );
          // Build and execute the update statement.
          $this->createUpdateStatement()
            ->fields($fields)
            ->condition('id', $ids_chunk)
            ->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackDelete(array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build and execute the update statement.
          $this->createDeleteStatement()
            ->condition('id', $ids_chunk)
            ->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Build and execute the update statement.
        $this->createDeleteStatement()->execute();
        // Indicate success operation.
        $success = TRUE;
      }
      catch (Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedIds($limit = -1) {
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly() && !$index->isNew()) {
      // Build the select statement.
      $statement = $this->createSelectStatement();
      $statement->fields('search_api_item', array('id'));
      $statement->condition('state', ItemStates::INDEXED, '<>');
      $statement->orderBy('changed', 'ASC');
      $statement->orderBy('state', 'ASC');
      // Check if the resultset needs to be limited.
      if ($limit > -1) {
        // Limit the number of results to the given value.
        $statement->range(0, $limit);
      }
      return $statement->execute()->fetchCol();
    }
    return array();
  }

}
