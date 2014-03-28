<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Tracker\TrackerInterface.
 */

namespace Drupal\search_api\Datasource\Tracker;

/**
 * Interface which describes a datasource tracker.
 */
interface TrackerInterface {

  /**
   * Track IDs as inserted.
   *
   * @param array $ids
   *   An array of item IDs.
   *
   * @return boolean
   *   TRUE if successful, otherwise FALSE.
   */
  public function trackInsert(array $ids);

  /**
   * Track IDs as updated.
   *
   * @param array $ids
   *   An array of item IDs, or NULL to mark all items as changed.
   *
   * @return boolean
   *   TRUE if successful, otherwise FALSE.
   */
  public function trackUpdate(array $ids = NULL);

  /**
   * Track IDs as indexed.
   *
   * @param array $ids
   *   An array of item IDs.
   *
   * @return boolean
   *   TRUE if successful, otherwise FALSE.
   */
  public function trackIndexed(array $ids);

  /**
   * Track IDs as deleted.
   *
   * @param array|NULL $ids
   *   An array of item IDs.
   */
  public function trackDelete(array $ids = NULL);

  /**
   * Clear all tracked items.
   *
   * @return boolean
   *   TRUE if successful, otherwise FALSE.
   */
  public function clear();

  /**
   * Get the status information.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerStatusInterface
   *   An instance of TrackerStatusInterface.
   */
  public function getStatus();

  /**
   * Get a list of IDs that need to be indexed.
   *
   * If possible, completely unindexed items should be returned before items
   * that were indexed but later changed. Also, items that were changed longer
   * ago should be favored.
   *
   * @param integer $limit
   *   Optional. The maximum number of items to return. Negative values mean
   *   "unlimited". Defaults to all changed items.
   *
   * @return array
   *   An array of item IDs that need to be indexed for the given index.
   */
  public function getChanged($limit = -1);

}
