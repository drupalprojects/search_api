<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Tracker\DefaultTracker.
 */

namespace Drupal\search_api\Plugin\SearchApi\Tracker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\search_api\Tracker\TrackerPluginBase;
use Drupal\search_api\Datasource\DatasourceInterface;

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
    // Perform default instance construction.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Setup object members.
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
            ->fields(array('item_id', 'index_id', 'changed'));
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
   * @return \Drupal\Core\Database\Query\Select
   *   An instance of Select.
   */
  protected function createRemainingItemsStatement() {
    // Build the select statement.
    // @todo: Should filter on the datasource once multiple datasources are
    // supported.
    $statement = $this->createSelectStatement();
    // Only the item ID is needed.
    $statement->fields('sai', array('item_id'));
    // Exclude items marked as indexed.
    $statement->condition('sai.changed', 0, '>');
    // Sort items by changed timestamp.
    $statement->orderBy('sai.changed', 'ASC');

    return $statement;
  }

  /**
   * {@inheritdoc}
   */
  public function trackInserted(DatasourceInterface $datasource, array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Catch any exception that may occur during insert.
      try {
        // Get the index ID.
        $index_id = $index->id();
        // Iterate through the IDs in chunks of 1000 items.
        foreach (array_chunk($ids, 1000) as $ids_chunk) {
          // Build the insert statement.
          $statement = $this->createInsertStatement();
          // Iterate through the chunked IDs.
          foreach ($ids_chunk as $item_id) {
            // Add the item ID to the insert statement.
            // @todo: Once multiple datasource are support we should include it.
            $statement->values(array(
              'item_id' => $item_id,
              'index_id' => $index_id,
              'changed' => REQUEST_TIME,
            ));
          }
          // Execute the statement.
          $statement->execute();
        }
        // Mark operation as successful.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback any changes made to the database.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackUpdated(DatasourceInterface $datasource, array $ids = NULL) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Catch any exception that may occur during insert.
      try {
        // Group the item IDs in chunks of maximum 1000 entries.
        $ids_chunks = ($ids !== NULL ? array_chunk($ids, 1000) : array(NULL));
        // Iterate through the IDs in chunks of 1000 items.
        foreach ($ids_chunks as $ids_chunk) {
          // Build the update statement.
          // @todo: Should include the datasource as filter once multiple
          // datasources are supported.
          $statement = $this->createUpdateStatement();
          // Set changed value to current REQUEST_TIME.
          $statement->fields(array('changed' => REQUEST_TIME));
          // Check whether specific items should be updated.
          if ($ids_chunk) {
            $statement->condition('item_id', $ids_chunk);
          }
          // Execute the statement.
          $statement->execute();
        }
        // Mark operation as successful.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback any changes made to the database.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackIndexed(DatasourceInterface $datasource, array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Catch any exception that may occur during insert.
      try {
        // Group the item IDs in chunks of maximum 1000 entries.
        $ids_chunks = ($ids !== NULL ? array_chunk($ids, 1000) : array(NULL));
        // Iterate through the IDs in chunks of 1000 items.
        foreach ($ids_chunks as $ids_chunk) {
          // Build the update statement.
          // @todo: Should include the datasource as filter once multiple
          // datasources are supported.
          $statement = $this->createUpdateStatement();
          // Set changed value to 0 which idicates the item was indexed.
          $statement->fields(array('changed' => 0));
          // Ensure only specified items get marked as indexed.
          $statement->condition('item_id', $ids_chunk);
          // Execute the statement.
          $statement->execute();
        }
        // Mark operation as successful.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback any changes made to the database.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function trackDeleted(DatasourceInterface $datasource, array $ids = NULL) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Catch any exception that may occur during insert.
      try {
        // Only perform the delete statement if at lease one ID is present or
        // all items should be deleted.
        if ($ids === NULL || $ids) {
          // Group the item IDs in chunks of maximum 1000 entries.
          $ids_chunks = ($ids !== NULL ? array_chunk($ids, 1000) : array(NULL));
          // Iterate through the IDs in chunks of 1000 items.
          foreach ($ids_chunks as $ids_chunk) {
            // Build the delete statement.
            // @todo: Should include the datasource as filter once multiple
            // datasources are supported.
            $statement = $this->createDeleteStatement();
            // Check whether specific items should be removed.
            if ($ids_chunk) {
              $statement->condition('item_id', $ids_chunk);
            }
            // Execute the statement.
            $statement->execute();
          }
        }
        // Mark operation as successful.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback any changes made to the database.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItems($limit = -1, $datasource_id = NULL) {
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Create the remaining items statement.
      // @todo: Should include the datasource once multiple datasources are
      // supported.
      $statement = $this->createRemainingItemsStatement();
      // Check whether a range should be applied.
      if ($limit > -1) {
        $statement->range(0, $limit);
      }
      // @todo: Default is temporarly used because multiple datasources
      // are currently not supported and ignored.
      return array('default' => $statement->execute()->fetchCol());
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItemsCount(DatasourceInterface $datasource = NULL) {
    // Get the index.
    $index = $this->getIndex();
    // Check whether the index is enabled.
    if (!$index->isNew() && $index->status()) {
      // @todo: Should include the datasource as filter once multiple
      // datasources are supported.
      return (int) $this->createRemainingItemsStatement()
              ->countQuery()
              ->execute()
              ->fetchField();
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalItemsCount(DatasourceInterface $datasource = NULL) {
    // Get the index.
    $index = $this->getIndex();
    // Check whether the index is enabled.
    if (!$index->isNew() && $index->status()) {
      // @todo: Should include the datasource as filter once multiple
      // datasources are supported.
      return (int) $this->createSelectStatement()
              ->countQuery()
              ->execute()
              ->fetchField();
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedItemsCount(DatasourceInterface $datasource = NULL) {
    // Get the index.
    $index = $this->getIndex();
    // Check whether the index is enabled.
    if (!$index->isNew() && $index->status()) {
      // Create the select statement. @todo: Should include the datasource once
      // multiple datasources are supported.
      $statement = $this->createSelectStatement();
      // Filter on indexed items.
      $statement->condition('sai.changed', 0);
      // Get the number of indexed items.
      return (int) $statement
              ->countQuery()
              ->execute()
              ->fetchField();
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled and writable.
    if (!$index->isNew() && $index->status() && !$index->isReadOnly()) {
      // Start a database transaction.
      $transaction = $this->getDatabaseConnection()->startTransaction();
      // Catch any exception that may occur during insert.
      try {
        // Remove all items for the current index.
        $this->createDeleteStatement()->execute();
        // Mark operation as successful.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback any changes made to the database.
        $transaction->rollback();
      }
    }
    return $success;
  }

}
