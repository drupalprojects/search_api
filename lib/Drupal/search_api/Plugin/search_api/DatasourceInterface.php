<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\DatasourceInterface.
 */

namespace Drupal\search_api\Plugin\search_api;

/**
 * Interface for all data source controllers for Search API indexes.
 *
 * Data source controllers encapsulate all operations specific to an item type.
 * They are used for loading items, extracting item data, keeping track of the
 * item status, etc.
 *
 * All methods of the data source may throw exceptions of type
 * DatasourceException if any exception or error state is encountered.
 */
interface DatasourceInterface {

  /**
   * Constructor for a data source controller.
   *
   * @param $type
   *   The item type for which this controller is created.
   */
  public function __construct($type);

  /**
   * Return information on the ID field for this controller's type.
   *
   * @return array
   *   An associative array containing the following keys:
   *   - key: The property key for the ID field, as used in the item wrapper.
   *   - type: The type of the ID field. Has to be one of the types from
   *     search_api_field_types(). List types ("list<*>") are not allowed.
   */
  public function getIdFieldInfo();

  /**
   * Load items of the type of this data source controller.
   *
   * @param array $ids
   *   The IDs of the items to laod.
   *
   * @return array
   *   The loaded items, keyed by ID.
   */
  public function loadItems(array $ids);

  /**
   * Get a metadata wrapper for the item type of this data source controller.
   *
   * @param $item
   *   Unless NULL, an item of the item type for this controller to be wrapped.
   * @param array $info
   *   Optionally, additional information that should be used for creating the
   *   wrapper. Uses the same format as entity_metadata_wrapper().
   *
   * @return EntityMetadataWrapper
   *   A wrapper for the item type of this data source controller, according to
   *   the info array, and optionally loaded with the given data.
   *
   * @see entity_metadata_wrapper()
   */
  public function getMetadataWrapper($item = NULL, array $info = array());

  /**
   * Get the unique ID of an item.
   *
   * @param $item
   *   An item of this controller's type.
   *
   * @return
   *   Either the unique ID of the item, or NULL if none is available.
   */
  public function getItemId($item);

  /**
   * Get a human-readable label for an item.
   *
   * @param $item
   *   An item of this controller's type.
   *
   * @return
   *   Either a human-readable label for the item, or NULL if none is available.
   */
  public function getItemLabel($item);

  /**
   * Get a URL at which the item can be viewed on the web.
   *
   * @param $item
   *   An item of this controller's type.
   *
   * @return
   *   Either an array containing the 'path' and 'options' keys used to build
   *   the URL of the item, and matching the signature of url(), or NULL if the
   *   item has no URL of its own.
   */
  public function getItemUrl($item);

  /**
   * Initialize tracking of the index status of items for the given indexes.
   *
   * All currently known items of this data source's type should be inserted
   * into the tracking table for the given indexes, with status "changed". If
   * items were already present, these should also be set to "changed" and not
   * be inserted again.
   *
   * @param array $indexes
   *   The Index objects for which item tracking should be initialized.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function startTracking(array $indexes);

  /**
   * Stop tracking of the index status of items for the given indexes.
   *
   * The tracking tables of the given indexes should be completely cleared.
   *
   * @param array $indexes
   *   The Index objects for which item tracking should be stopped.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function stopTracking(array $indexes);

  /**
   * Start tracking the index status for the given items on the given indexes.
   *
   * @param array $item_ids
   *   The IDs of new items to track.
   * @param array $indexes
   *   The indexes for which items should be tracked.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function trackItemInsert(array $item_ids, array $indexes);

  /**
   * Set the tracking status of the given items to "changed"/"dirty".
   *
   * Unless $dequeue is set to TRUE, this operation is ignored for items whose
   * status is not "indexed".
   *
   * @param $item_ids
   *   Either an array with the IDs of the changed items. Or FALSE to mark all
   *   items as changed for the given indexes.
   * @param array $indexes
   *   The indexes for which the change should be tracked.
   * @param $dequeue
   *   If set to TRUE, also change the status of queued items.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function trackItemChange($item_ids, array $indexes, $dequeue = FALSE);

  /**
   * Set the tracking status of the given items to "queued".
   *
   * Queued items are not marked as "dirty" even when they are changed, and they
   * are not returned by the getChangedItems() method.
   *
   * @param $item_ids
   *   Either an array with the IDs of the queued items. Or FALSE to mark all
   *   items as queued for the given indexes.
   * @param Index $index
   *   The index for which the items were queued.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function trackItemQueued($item_ids, Index $index);

  /**
   * Set the tracking status of the given items to "indexed".
   *
   * @param array $item_ids
   *   The IDs of the indexed items.
   * @param Index $indexes
   *   The index on which the items were indexed.
   *
   * @throws DatasourceException
   *   If the index doesn't use the same item type as this controller.
   */
  public function trackItemIndexed(array $item_ids, Index $index);

  /**
   * Stop tracking the index status for the given items on the given indexes.
   *
   * @param array $item_ids
   *   The IDs of the removed items.
   * @param array $indexes
   *   The indexes for which the deletions should be tracked.
   *
   * @throws DatasourceException
   *   If any of the indexes doesn't use the same item type as this controller.
   */
  public function trackItemDelete(array $item_ids, array $indexes);

  /**
   * Get a list of items that need to be indexed.
   *
   * If possible, completely unindexed items should be returned before items
   * that were indexed but later changed. Also, items that were changed longer
   * ago should be favored.
   *
   * @param Index $index
   *   The index for which changed items should be returned.
   * @param $limit
   *   The maximum number of items to return. Negative values mean "unlimited".
   *
   * @return array
   *   The IDs of items that need to be indexed for the given index.
   */
  public function getChangedItems(Index $index, $limit = -1);

  /**
   * Get information on how many items have been indexed for a certain index.
   *
   * @param Index $index
   *   The index whose index status should be returned.
   *
   * @return array
   *   An associative array containing two keys (in this order):
   *   - indexed: The number of items already indexed in their latest version.
   *   - total: The total number of items that have to be indexed for this
   *     index.
   *
   * @throws DatasourceException
   *   If the index doesn't use the same item type as this controller.
   */
  public function getIndexStatus(Index $index);

  /**
   * Get the entity type of items from this datasource.
   *
   * @return string|null
   *   An entity type string if the items provided by this datasource are
   *   entities; NULL otherwise.
   */
  public function getEntityType();
}
