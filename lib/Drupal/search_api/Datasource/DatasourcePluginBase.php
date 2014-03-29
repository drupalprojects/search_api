<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginBase.
 */

namespace Drupal\search_api\Datasource;

use Drupal\Core\Database\Connection;
use Drupal\search_api\Plugin\IndexPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other datasources may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_datasource_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the datasource.
 * - label: The human-readable name of the datasource, translated.
 * - description: A human-readable description for the datasource, translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiDatasource(
 *   id = "my_datasource",
 *   label = @Translation("My item type"),
 *   description = @Translation("Exposes my custom items as an item type."),
 * )
 * @endcode
 */
abstract class DatasourcePluginBase extends IndexPluginBase implements DatasourceInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $databaseConnection;


  /**
   * The table to use for tracking.
   *
   * @var string
   */
  protected $table;

  /**
   * Create a DatasourcePluginBase object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection to the database.
   * @param string $table
   *   The table to use for tracking.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(Connection $connection, array $configuration, $plugin_id, array $plugin_definition, $table = 'search_api_item') {
    // Initialize the parent chain of objects.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Setup object members.
    $this->databaseConnection = $connection;
    $this->table = $table;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    /** @var $connection \Drupal\Core\Database\Connection */
    $connection = $container->get('database');
    // @todo: Make this more dynamic
    $table = 'search_api_item';
    return new static($connection, $table, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Retrieves the index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * Retrieves the database connection
   *
   * @return \Drupal\Core\Database\Connection
   *   An instance of Connection.
   */
  protected function getDatabaseConnection() {
    return $this->databaseConnection;
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
   * {@inheritdoc}
   */
  public function trackInsert(array $ids) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly()) {
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
              'item_id' => $item_id,
              'index_id' => $index_id,
              'changed' => 0,
            ));
          }
          // Execute the insert statement.
          $statement->execute();
        }
        // Indicate successful operation.
        $success = TRUE;
      }
      catch (\Exception $ex) {
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
  public function trackUpdate(array $ids = NULL) {
    // Initialize the success variable to FALSE.
    $success = FALSE;
    // Get the index.
    $index = $this->getIndex();
    // Check if the index is enabled, writable and exists.
    if ($index->status() && !$index->isReadOnly()) {
      // Get the database connection.
      $connection = $this->getDatabaseConnection();
      // Start a database transaction.
      $transaction = $connection->startTransaction();
      // Catch any exception that may occur during update.
      try {
        // Iterate through the IDs in chunks of 1000 items.
        $ids_chunks = $ids ? array_chunk($ids, 1000) : array(NULL);
        foreach ($ids_chunks as $ids_chunk) {
          // Build the fields which need to be updated.
          // Build the update statement.
          $statement = $this->createUpdateStatement()
            ->fields(array(
              'changed' => REQUEST_TIME,
            ));
          if ($ids_chunk) {
            $statement->condition('item_id', $ids_chunk);
          }
          // Execute the update statement.
          $statement->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (\Exception $ex) {
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
            'changed' => REQUEST_TIME,
          );
          // Build and execute the update statement.
          $this->createUpdateStatement()
            ->fields($fields)
            ->condition('item_id', $ids_chunk)
            ->execute();
        }
        // Indicate success operation.
        $success = TRUE;
      }
      catch (\Exception $ex) {
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
  public function trackDelete(array $ids = NULL) {
    $delete_statement = $this->createDeleteStatement();
    if (isset($ids)) {
      $delete_statement->condition('item_id', $ids, 'IN');
    }
    $delete_statement->execute();
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
        $this->trackDelete();
        // Indicate success operation.
        $success = TRUE;
      }
      catch (\Exception $ex) {
        // Log the exception to watchdog.
        watchdog_exception('Search API', $ex);
        // Rollback the transaction.
        $transaction->rollback();
      }
    }
    return $success;
  }

  /**
   * Creates the base query so we can get al the items or just the count
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   */
  private function getRemainingItemsQuery() {
    // Get the index and its last state
    $index = $this->getIndex();

      // Get $last_entity_id and $last_changed.
      $last_indexed = $index->getLastIndexed();

      // Build the select statement.
      $statement = $this->createSelectStatement();


      $changed = $last_indexed['changed'];
      $item_id = $last_indexed['item_id'];

      // Find the next batch of entities to index for this entity type. Note that
      // for ordering we're grabbing the oldest first and then ordering by ID so
      // that we get a definitive order.
      // Also note that we fetch ALL fields from the indexer table
      $alias = $statement->addExpression('LENGTH(item_id)', 'item_id_length');

      $statement
        ->fields('sai', array('item_id'))
        ->condition(db_or()
            ->condition('sai.changed', $changed, '>')
            // Tie breaker for entities that were changed at exactly
            // the same second as the last indexed entity
            ->condition(db_and()
                ->condition('sai.changed', $changed, '=')
                ->condition('sai.item_id', $item_id, '>')
            )
        )
        // It is important that everything is indexed in order of changed date and
        // then on entity_id because otherwise the conditions above will not match
        // correctly
        ->orderBy('sai.changed', 'ASC')
        ->orderBy($alias, 'ASC')
        ->orderBy('sai.item_id', 'ASC');

      return $statement;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItems($limit = -1) {
    // Check if the index is enabled, writable and exists.
    if ($this->index->status() && !$this->index->isReadOnly()) {
      $statement = $this->getRemainingItemsQuery();
      // Check if the result set needs to be limited.
      if ($limit > -1) {
        // Limit the number of results to the given value.
        $statement->range(0, $limit);
      }
      return $statement->execute()->fetchCol();
    }
    else {
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedItemsCount() {
    // Check if the index is enabled and exists.
    if ($this->index->status()) {
      $total = $this->getTotalItemsCount();
      $remaining = $this->getRemainingItemsCount();
      $indexed = $total - $remaining;
      return $indexed;
    }
    else {
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingItemsCount() {
    // Check if the index is enabled, writable and exists.
    if ($this->index->status()) {
      $statement = $this->getRemainingItemsQuery();
      return $statement->countQuery()->execute()->fetchField();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalItemsCount() {
    // Check if the index is enabled and exists.
    if ($this->index->status()) {
      return $this->createSelectStatement()
        ->countQuery()
        ->execute()->
        fetchField();
    }
  }
}
