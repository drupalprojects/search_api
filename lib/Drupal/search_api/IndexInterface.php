<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexInterface.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Plugin\search_api\QueryInterface;

/**
 * Provides an interface defining a search index entity.
 */
interface IndexInterface extends ConfigEntityInterface {

  /**
   * Retrieves the this index's read-only state.
   *
   * @return bool
   *   TRUE if this index is read-only, FALSE otherwise.
   */
  public function readOnly();

  /**
   * Sets this index's read-only state.
   *
   * @param bool $read_only
   *   TRUE if this index should be marked as read-only, FALSE otherwise.
   */
  public function setReadOnly($read_only);

  /**
   * Get the item type of items in this index.
   *
   * @return string
   *   The type of items in this index.
   *
   * @see search_api_get_item_type_info()
   */
  public function getItemType();

  /**
   * Get the entity type of items in this index.
   *
   * @return string|null
   *   An entity type string if the items in this index are entities; NULL
   *   otherwise.
   */
  public function getEntityType();

  /**
   * Get the controller object of the data source used by this index.
   *
   * @return \Drupal\search_api\Plugin\search_api\DatasourceInterface
   *   The data source controller for this index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If the specified item type or data source doesn't exist or is invalid.
   */
  public function datasource();

  /**
   * Retrieves the ID of the server this index lies on.
   *
   * @return string|null
   *   The ID of the server this index lies on, or NULL if it does not lie on
   *   any.
   */
  public function serverId();

  /**
   * Retrieves the server entity this index lies on.
   *
   * @param bool $reset
   *   (optional) Whether to reset the internal cache. Set to TRUE when the
   *   index's $server property has just changed.
   *
   * @return \Drupal\search_api\ServerInterface|null
   *   The server associated with this index, or NULL if this index currently
   *   doesn't lie on a server.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If $this->server is set, but no server with that ID exists.
   */
  public function server($reset = FALSE);

  /**
   * Retrieves an option from this index.
   *
   * @param string $name
   *   The name of the option to retrieve.
   * @param mixed $default
   *   The value to return if the specified option is not set.
   *
   * @return mixed
   *   The value of the option with the specified name, if set. $default
   *   otherwise.
   */
  public function getOption($name, $default = NULL);

  /**
   * Retrieves all options set for this index.
   *
   * @return array
   *   An associative array of all index options.
   */
  public function getOptions();

  /**
   * Puts all of this index's items into the indexing queue.
   *
   * Called when the index is created or enabled.
   */
  public function queueItems();

  /**
   * Clear this index's indexing queue.
   *
   * Called when the index is disabled or deleted.
   */
  public function dequeueItems();

  /**
   * Schedules this search index for re-indexing.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function reindex();

  /**
   * Clears this search index and schedules all of its items for re-indexing.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function clear();

  /**
   * Create a query object for this index.
   *
   * @param array $options
   *   Associative array of options configuring this query. See
   *   \Drupal\search_api\Plugin\search_api\QueryInterface::__construct().
   *
   * @return \Drupal\search_api\Plugin\search_api\QueryInterface
   *   A query object for searching this index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If the index is currently disabled.
   */
  public function query(array $options = array());

  /**
   * Indexes items on this index.
   *
   * Will return an array of IDs of items that should be marked as indexed –
   * i.e., items that were either rejected by a data-alter callback or were
   * successfully indexed.
   *
   * @param array $items
   *   An array of items to index.
   *
   * @return array
   *   An array of the IDs of all items that should be marked as indexed.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If the index is disabled, no fields are set or any other indexing error
   *   occured.
   */
  public function index(array $items);

  /**
   * Alters the property information of items.
   *
   * Lets all enabled processors alter the property information for items in
   * this index.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $wrapper
   *   The wrapped data.
   * @param array $property_info
   *   The original property info.
   *
   * @return array
   *   The altered property info.
   */
  public function propertyInfoAlter(ComplexDataInterface $wrapper, array $property_info);

  /**
   * Loads all enabled processors for this index in proper order.
   *
   * @return array
   *   All enabled processors for this index, as
   *   \Drupal\search_api\Plugin\search_api\ProcessorInterface objects.
   */
  public function getProcessors();

  /**
   * Preprocesses data items for indexing.
   *
   * Lets all enabled processors for this index preprocess the indexed data.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocesses a search query.
   *
   * Lets all enabled processors for this index preprocess the search query.
   *
   * @param \Drupal\search_api\Plugin\search_api\QueryInterface $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(QueryInterface $query);

  /**
   * Postprocesses search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results. See
   *   \Drupal\search_api\Plugin\search_api\QueryInterface::execute() for the
   *   detailed format.
   * @param \Drupal\search_api\Plugin\search_api\QueryInterface $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query);

  /**
   * Returns a list of all known fields for this index.
   *
   * @param bool $only_indexed
   *   (optional) Return only indexed fields, not all known fields.
   * @param bool $get_additional
   *   (optional) Return not only known/indexed fields, but also related
   *   entities whose fields could additionally be added to the index.
   *
   * @return array
   *   An array of all known fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - name: The human-readable name for the field.
   *   - description: A description of the field, if available.
   *   - indexed: Boolean indicating whether the field is indexed or not.
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: A boost value for terms found in this field during searches.
   *     Usually only relevant for fulltext fields.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then contain "integer", meaning that
   *     servers will ignore this and merely index the entity's ID. Components
   *     displaying this field, though, are advised to use the entity label
   *     instead of the ID.
   *   If $get_additional is TRUE, this array is encapsulated in another
   *   associative array, which contains the above array under the "fields" key,
   *   and a list of related entities (field keys mapped to names) under the
   *   "additional_fields" key.
   */
  public function getFields($only_indexed = TRUE, $get_additional = FALSE);

  /**
   * Convenience method for getting all of this index's fulltext fields.
   *
   * @param bool $only_indexed
   *   (optional) If set to TRUE, only the indexed fulltext fields will be
   *   returned. Otherwise, this method will return all available fulltext
   *   fields.
   *
   * @return array
   *   An array containing all (or all indexed) fulltext fields defined for this
   *   index.
   */
  public function getFulltextFields($only_indexed = TRUE);

  /**
   * Retrieves the cache backend to use for this index.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend to use for this index.
   */
  public function cache();

  /**
   * Get the cache ID prefix used for this index's caches.
   *
   * @param string $type
   *   The type of cache. Currently only "fields" is used.
   *
   * @return
   *   The cache ID (prefix) for this index's caches.
   */
  public function getCacheId($type = 'fields');

  /**
   * Helper function for creating an entity metadata wrapper appropriate for
   * this index.
   *
   * @param object|null $item
   *   (optional) Unless NULL, an item of this index's item type which should be
   *   wrapped.
   * @param bool $alter
   *   (optional) Whether to alter the property information according to this
   *   index's enabled processors.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface
   *   A wrapper for the item type of this index, optionally loaded with the
   *   given data and having additional fields according to the enabled
   *   processors for this index.
   */
  public function entityWrapper($item = NULL, $alter = TRUE);

  /**
   * Helper method to load items from the type lying on this index.
   *
   * @param array $ids
   *   The IDs of the items to load.
   *
   * @return array
   *   The requested items, as loaded by the data source.
   *
   * @see \Drupal\search_api\Plugin\search_api\DatasourceInterface::loadItems()
   */
  public function loadItems(array $ids);

  /**
   * Reset internal static caches.
   *
   * Should be used when things like fields or data alterations change to avoid
   * using stale data.
   */
  public function resetCaches();

}
