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
   * Get the number of changed items.
   *
   * @param boolean $queued
   *   Optional. Indicates the queued items should be included in the changed
   *   count. Defaults to FALSE.
   *
   * @return integer
   *   The number of changed items.
   */
  public function getChangedCount($queued = FALSE);

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
