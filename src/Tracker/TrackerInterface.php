<?php

/**
 * @file
 * Contains \Drupal\search_api\Tracker\TrackerInterface.
 */

namespace Drupal\search_api\Tracker;

use Drupal\search_api\Plugin\IndexPluginInterface;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * Interface which describes a tracker plugin for Search API.
 */
interface TrackerInterface extends IndexPluginInterface {

  /**
   * Track items being inserted.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   An instance of DatasourceInterface which is registering the items for
   *   tracking.
   * @param array $ids
   *   An array of item IDs.
   *
   * @return bool
   *   TRUE if the items are being tracked, otherwise FALSE.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   Can be thrown when the datasource is not owned by the index.
   */
  public function trackInserted(DatasourceInterface $datasource, array $ids);

  /**
   * Track items being updated.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   An instance of DatasourceInterface which is updating the items.
   * @param array|null $ids
   *   (optional) An array of item IDs. Defaults to all tracked items.
   *
   * @return bool
   *   TRUE if the items were updated, otherwise FALSE.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   Can be thrown when the datasource is not owned by the index.
   */
  public function trackUpdated(DatasourceInterface $datasource, array $ids = NULL);

  /**
   * Track items being indexed.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   An instance of DatasourceInterface which owns the indexed items.
   * @param array $ids
   *   An array of item IDs.
   *
   * @return bool
   *   TRUE if the items were marked as indexed, otherwise FALSE.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   Can be thrown when the datasource is not owned by the index.
   */
  public function trackIndexed(DatasourceInterface $datasource, array $ids);

  /**
   * Track items being deleted.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   An instance of DatasourceInterface which owns the items.
   * @param array|null $ids
   *   (optional) An array of item IDs. Defaults to all tracked items.
   *
   * @return bool
   *   TRUE if the items were removed, otherwise FALSE.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   Can be thrown when the datasource is not owned by the index.
   */
  public function trackDeleted(DatasourceInterface $datasource, array $ids = NULL);

  /**
   * Get a list of item IDs that need to be indexed.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource to filter by when retrieving the remaining
   *   items.
   * @param int $limit
   *   (optional) The maximum number of items to return. Negative value means
   *   "unlimited". Defaults to all pending items.
   *
   * @return array
   *   An associative array of item IDs, keyed by the item type.
   */
  public function getRemainingItems(DatasourceInterface $datasource = NULL, $limit = -1);

  /**
   * Get the total number of pending items.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource to filter the total number of pending items by.
   *
   * @return int
   *   The total number of pending items.
   */
  public function getRemainingItemsCount(DatasourceInterface $datasource = NULL);

  /**
   * Get the total number of items that are being monitored.
   * 
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource to filter the total number of items by.
   * 
   * @return int
   *   The total number of items that are being monitored.
   */
  public function getTotalItemsCount(DatasourceInterface $datasource = NULL);

  /**
   * Get the total number of indexed items.
   * 
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource to filter the total number of indexed items by.
   * 
   * @return int
   *   The total number of items that are indexed.
   */
  public function getIndexedItemsCount(DatasourceInterface $datasource = NULL);

  /**
   * Clear all tracked items.
   *
   * @return bool
   *   TRUE if the operation was successful, otherwise FALSE.
   */
  public function clear();

}
