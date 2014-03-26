<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Tracker\TrackerStatusInterface.
 */

namespace Drupal\search_api\Datasource\Tracker;

/**
 * Interface which describes the status of an datasource tracker.
 */
interface TrackerStatusInterface {

  /**
   * Retrieves the number of indexed items.
   *
   * @return int
   *   The number of indexed items.
   */
  public function getIndexedCount();

  /**
   * Retrieves the number of changed items.
   *
   * @return int
   *   The number of changed items.
   */
  public function getChangedCount();

  /**
   * Retrieves the total number of items that have to be indexed.
   *
   * @return int
   *   The total number of items that have to be indexed.
   */
  public function getTotalCount();

}
