<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Tracker\DefaultTrackerStatus.
 */

namespace Drupal\search_api\Datasource\Tracker;

use Drupal\Core\Database\Connection;
use Drupal\search_api\Index\IndexInterface;

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
   * The table to use for tracking items.
   *
   * @var string
   */
  protected $table;

  /**
   * Creates a DefaultTrackerStatus object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection to the database.
   * @param string $table
   *   (optional) The table to use for tracking items.
   */
  public function __construct(IndexInterface $index, Connection $connection, $table = 'search_api_item') {
    // Setup object members.
    $this->index = $index;
    $this->databaseConnection = $connection;
    $this->table = $table;
  }

  /**
   * Retrieves the index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   */
  protected function getIndex() {
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
    $select = $this->getDatabaseConnection()->select('search_api_item', 'i');
    $select->condition('index', $this->getIndex()->id());
    return $select;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedCount() {
    $select = $this->createSelectStatement();
    $select->condition('changed', 0);
    return $select->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedCount($queued = FALSE) {
    $select = $this->createSelectStatement();
    $select->condition('changed', 0, '<>');
    return $select->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalCount() {
    return $this->createSelectStatement()
      ->countQuery()
      ->execute()->
      fetchField();
  }

}
