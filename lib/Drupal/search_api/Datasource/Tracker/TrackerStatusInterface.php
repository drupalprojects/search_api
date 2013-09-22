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
   * Get the number of indexed items.
   *
   * @return integer
   *   The number of indexed items.
   */
  public function getIndexedCount();

  /**
   * Get the number of dirty items.
   *
   * @return integer
   *   The number of dirty items.
   */
  public function getChangedCount();

  /**
   * Get the number of queued items.
   *
   * @return integer
   *   The number of queued items.
   */
  public function getQueuedCount();

  /**
   * Get the total number of items that have to be indexed.
   *
   * @return integer
   *   The total number of items that have to be indexed.
   */
  public function getTotalCount();

}
