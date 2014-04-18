<?php

/**
 * @file
 * Contains \Drupal\search_api\Tracker\TrackerInterface.
 */

namespace Drupal\search_api\Tracker;

use Drupal\search_api\Plugin\IndexPluginInterface;

/**
 * Interface which describes a tracker plugin for Search API.
 */
interface TrackerInterface extends IndexPluginInterface {

  /**
   * Tracks items being inserted.
   *
   * @param string $datasource
   *   The datasource to which the inserted items belong.
   * @param array $ids
   *   An array of item IDs.
   */
  public function trackInserted($datasource, array $ids);

  /**
   * Tracks items being updated.
   *
   * @param string|null $datasource
   *   (optional) The datasource to which the updated items belong. If NULL,
   *   $ids is ignored and all tracked items for this index are marked for
   *   re-indexing.
   * @param array|null $ids
   *   (optional) An array of item IDs. Defaults to all tracked items.
   */
  public function trackUpdated($datasource = NULL, array $ids = NULL);

  /**
   * Tracks items being indexed.
   *
   * @param string $datasource
   *   The datasource to which the indexed items belong.
   * @param array $ids
   *   An array of item IDs.
   */
  public function trackIndexed($datasource, array $ids);

  /**
   * Tracks items being deleted.
   *
   * @param string|null $datasource
   *   (optional) The datasource to which the deleted items belong. If NULL,
   *   $ids is ignored and all tracked items are deleted for this index.
   * @param array|null $ids
   *   (optional) An array of item IDs. Defaults to all tracked items.
   */
  public function trackDeleted($datasource = NULL, array $ids = NULL);

  /**
   * Retrieves a list of item IDs that need to be indexed.
   *
   * @param int $limit
   *   (optional) The maximum number of items to return. A negative value means
   *   "unlimited".
   * @param string|null $datasource
   *   (optional) If specified, only items of the datasource with that ID are
   *   retrieved.
   *
   * @return array
   *   If no datasource ID was given, an associative array where the keys are
   *   datasource IDs and the values are arrays of item IDs to index for those
   *   datasources. Otherwise, an array of item IDs to index for the given
   *   datasource.
   */
  public function getRemainingItems($limit = -1, $datasource = NULL);

  /**
   * Retrieves the total number of pending items for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of pending items by.
   *
   * @return int
   *   The total number of pending items.
   */
  public function getRemainingItemsCount($datasource = NULL);

  /**
   * Retrieves the total number of items that are being tracked for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of items by.
   *
   * @return int
   *   The total number of items that are being monitored.
   */
  public function getTotalItemsCount($datasource = NULL);

  /**
   * Retrieves the total number of indexed items for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of indexed items by.
   *
   * @return int
   *   The number of items that have been indexed in their latest state for this
   *   index (and datasource, if specified).
   */
  public function getIndexedItemsCount($datasource = NULL);

}
