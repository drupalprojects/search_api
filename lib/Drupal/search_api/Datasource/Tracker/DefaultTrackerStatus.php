<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Tracker\DefaultTrackerStatus.
 */

namespace Drupal\search_api\Datasource\Tracker;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Database\Connection;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\Item\ItemStates;

/**
 * Default datasource tracker status which uses the database.
 */
class DefaultTrackerStatus implements TrackerStatusInterface {

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
   * Create a DefaultTrackerStatus object.
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
     ->fields('search_api_item', 'search_api_item')
     ->condition('index', $this->getIndex()->id())
     ->countQuery();
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedCount() {
    // Build the select statement.
    $statement = $this->createSelectStatement();
    // Filter on indexed items.
    $statement->condition('state', ItemStates::INDEXED);
    // Get the number of indexed items.
    return $statement->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedCount() {
    // Build the select statement.
    $statement = $this->createSelectStatement();
    // Filter on dirty items.
    $statement->condition('state', ItemStates::DIRTY);
    // Get the number of dirty items.
    return $statement->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueuedCount() {
    // Build the select statement.
    $statement = $this->createSelectStatement();
    // Filter on queued items.
    $statement->condition('state', ItemStates::QUEUED);
    // Get the number of queued items.
    return $statement->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalCount() {
    return $this->createSelectStatement()->execute()->fetchField();
  }

}
