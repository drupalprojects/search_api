<?php
/**
 * @file
 * Contains \Drupal\search_api\Index\IndexTrackerInterface.
 */

namespace Drupal\search_api\Index;

/**
 * Interface which describes an index tracker.
 */
interface IndexTrackerInterface {

  /**
   * Get the index status information.
   *
   * @return \Drupal\search_api\Index\IndexStatusInterface
   *   An instance of IndexStatusInterface.
   */
  public function getIndexStatus();

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
  public function getChangedIds($limit = -1);

  // @todo: Add additional functionality like: clear, ...

}
