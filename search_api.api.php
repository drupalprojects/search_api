<?php

/**
 * @file
 * Hooks provided by the Search API module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Search API service info.
 *
 * Modules may implement this hook to alter the information that defines Search
 * API service.
 *
 * @param array $service_info
 *   The Search API service info array, keyed by service id.
 */
function hook_search_api_service_info_alter(array &$service_info) {
  foreach ($service_info as $id => $info) {
    $service_info[$id]['name'] = t('Test');
  }
}

/**
 * Define new types of items that can be searched.
 *
 * This hook allows modules to define their own item types, for which indexes
 * can then be created. (Note that the Search API natively provides support for
 * all entity types that specify property information, so they should not be
 * added here. You should therefore also not use an existing entity type as the
 * identifier of a new item type.)
 *
 * The main part of defining a new item type is implementing its data source
 * controller class, which is responsible for loading items, providing metadata
 * and tracking existing items. The module defining a certain item type is also
 * responsible for observing creations, updates and deletions of items of that
 * type and notifying the Search API of them by calling
 * search_api_track_item_insert(), search_api_track_item_change() and
 * search_api_track_item_delete(), as appropriate.
 * The only other restriction for item types is that they have to have a single
 * item ID field, with a scalar value. This is, e.g., used to track indexed
 * items.
 *
 * Note, however, that you can also define item types where some of these
 * conditions are not met, as long as you are aware that some functionality of
 * the Search API and related modules might then not be available for that type.
 *
 * @return array
 *   An associative array keyed by item type identifier, and containing type
 *   information arrays with at least the following keys:
 *   - name: A human-readable name for the type.
 *   - datasource controller: A class implementing the
 *     SearchApiDataSourceControllerInterface interface which will be used as
 *     the data source controller for this type.
 *   - entity_type: (optional) If the type represents entities, the entity type.
 *     This is used by SearchApiAbstractDataSourceController for determining the
 *     entity type of items. Other datasource controllers might ignore this.
 *   Other, datasource-specific settings might also be placed here. These should
 *   be specified with the data source controller in question.
 *
 * @see hook_search_api_item_type_info_alter()
 */
function hook_search_api_item_type_info() {
  // Copied from search_api_search_api_item_type_info().
  $types = array();

  foreach (entity_get_property_info() as $type => $property_info) {
    if ($info = entity_get_info($type)) {
      $types[$type] = array(
        'name' => $info['label'],
        'datasource controller' => 'SearchApiEntityDataSourceController',
        'entity_type' => $type,
      );
    }
  }

  return $types;
}

/**
 * Alter the item type info.
 *
 * Modules may implement this hook to alter the information that defines an
 * item type. All properties that are available in
 * hook_search_api_item_type_info() can be altered here.
 *
 * @param array $infos
 *   The item type info array, keyed by type identifier.
 *
 * @see hook_search_api_item_type_info()
 */
function hook_search_api_item_type_info_alter(array &$infos) {
  // Adds a boolean value is_entity to all type options telling whether the item
  // type represents an entity type.
  foreach ($infos as $type => $info) {
    $info['is_entity'] = (bool) entity_get_info($type);
  }
}

/**
 * Define new data types for indexed properties.
 *
 * New data types will appear as new option for the „Type“ field on indexes'
 * „Fields“ tabs. Whether choosing a custom data type will have any effect
 * depends on the server on which the data is indexed.
 *
 * @return array
 *   An array containing custom data type definitions, keyed by their type
 *   identifier and containing the following keys:
 *   - name: The human-readable name of the type.
 *   - fallback: (optional) One of the default data types (the keys from
 *     search_api_default_field_types()) which should be used as a fallback if
 *     the server doesn't support this data type. Defaults to "string".
 *   - conversion callback: (optional) If specified, a callback function for
 *     converting raw values to the given type, if possible. For the contract
 *     of such a callback, see example_data_type_conversion().
 *
 * @see hook_search_api_data_type_info_alter()
 * @see search_api_get_data_type_info()
 * @see example_data_type_conversion()
 */
function hook_search_api_data_type_info() {
  return array(
    'example_type' => array(
      'name' => t('Example type'),
      // Could be omitted, as "string" is the default.
      'fallback' => 'string',
      'conversion callback' => 'example_data_type_conversion',
    ),
  );
}

/**
 * Alter the data type info.
 *
 * Modules may implement this hook to alter the information that defines a data
 * type, or to add/remove some entirely. All properties that are available in
 * hook_search_api_data_type_info() can be altered here.
 *
 * @param array $infos
 *   The data type info array, keyed by type identifier.
 *
 * @see hook_search_api_data_type_info()
 */
function hook_search_api_data_type_info_alter(array &$infos) {
  $infos['example_type']['name'] .= ' 2';
}

/**
 * Allows you to log or alter the items that are indexed.
 *
 * Please be aware that generally preventing the indexing of certain items is
 * deprecated. This is better done with data alterations, which can easily be
 * configured and only added to indexes where this behaviour is wanted.
 * If your module will use this hook to reject certain items from indexing,
 * please document this clearly to avoid confusion.
 *
 * @param array $items
 *   The entities that will be indexed (before calling any data alterations).
 * @param SearchApiIndex $index
 *   The search index on which items will be indexed.
 */
function hook_search_api_index_items_alter(array &$items, SearchApiIndex $index) {
  foreach ($items as $id => $item) {
    if ($id % 5 == 0) {
      unset($items[$id]);
    }
  }
  example_store_indexed_entity_ids($index->item_type, array_keys($items));
}

/**
 * Allows modules to react after items were indexed.
 *
 * @param SearchApiIndex $index
 *   The used index.
 * @param array $item_ids
 *   An array containing the indexed items' IDs.
 */
function hook_search_api_items_indexed(SearchApiIndex $index, array $item_ids) {
  if ($index->getEntityType() == 'node') {
    // Flush page cache of the search page.
    cache_clear_all(url('search'), 'cache_page');
  }
}

/**
 * Lets modules alter a search query before executing it.
 *
 * @param SearchApiQueryInterface $query
 *   The SearchApiQueryInterface object representing the search query.
 */
function hook_search_api_query_alter(SearchApiQueryInterface $query) {
  // Exclude entities with ID 0. (Assume the ID field is always indexed.)
  if ($query->getIndex()->getEntityType()) {
    $info = entity_get_info($query->getIndex()->getEntityType());
    $query->condition($info['entity keys']['id'], 0, '!=');
  }
}

/**
 * Act on search servers when they are loaded.
 *
 * @param array $servers
 *   An array of loaded SearchApiServer objects.
 */
function hook_search_api_server_load(array $servers) {
  foreach ($servers as $server) {
    db_insert('example_search_server_access')
      ->fields(array(
        'server' => $server->machine_name,
        'access_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * A new search server was created.
 *
 * @param SearchApiServer $server
 *   The new server.
 */
function hook_search_api_server_insert(SearchApiServer $server) {
  db_insert('example_search_server')
    ->fields(array(
      'server' => $server->machine_name,
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * A search server was edited, enabled or disabled.
 *
 * @param SearchApiServer $server
 *   The edited server.
 */
function hook_search_api_server_update(SearchApiServer $server) {
  if ($server->name != $server->original->name) {
    db_insert('example_search_server_name_update')
      ->fields(array(
        'server' => $server->machine_name,
        'update_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * A search server was deleted.
 *
 * @param SearchApiServer $server
 *   The deleted server.
 */
function hook_search_api_server_delete(SearchApiServer $server) {
  db_insert('example_search_server_update')
    ->fields(array(
      'server' => $server->machine_name,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_server')
    ->condition('server', $server->machine_name)
    ->execute();
}

/**
* Define default search servers.
*
* @return array
*   An array of default search servers, keyed by machine names.
*
* @see hook_default_search_api_server_alter()
*/
function hook_default_search_api_server() {
  $defaults['main'] = entity_create('search_api_server', array(
    'name' => 'Main server',
    'machine_name' => 'main',// Must be same as the used array key.
    // Other properties ...
  ));
  return $defaults;
}

/**
* Alter default search servers.
*
* @param array $defaults
*   An array of default search servers, keyed by machine names.
*
* @see hook_default_search_api_server()
*/
function hook_default_search_api_server_alter(array &$defaults) {
  $defaults['main']->name = 'Customized main server';
}

/**
 * Act on search indexes when they are loaded.
 *
 * @param array $indexes
 *   An array of loaded SearchApiIndex objects.
 */
function hook_search_api_index_load(array $indexes) {
  foreach ($indexes as $index) {
    db_insert('example_search_index_access')
      ->fields(array(
        'index' => $index->machine_name,
        'access_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * A new search index was created.
 *
 * @param SearchApiIndex $index
 *   The new index.
 */
function hook_search_api_index_insert(SearchApiIndex $index) {
  db_insert('example_search_index')
    ->fields(array(
      'index' => $index->machine_name,
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * A search index was edited in any way.
 *
 * @param SearchApiIndex $index
 *   The edited index.
 */
function hook_search_api_index_update(SearchApiIndex $index) {
  if ($index->name != $index->original->name) {
    db_insert('example_search_index_name_update')
      ->fields(array(
        'index' => $index->machine_name,
        'update_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * A search index was scheduled for reindexing
 *
 * @param SearchApiIndex $index
 *   The edited index.
 * @param $clear
 *   Boolean indicating whether the index was also cleared.
 */
function hook_search_api_index_reindex(SearchApiIndex $index, $clear = FALSE) {
  db_insert('example_search_index_reindexed')
    ->fields(array(
      'index' => $index->id,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * A search index was deleted.
 *
 * @param SearchApiIndex $index
 *   The deleted index.
 */
function hook_search_api_index_delete(SearchApiIndex $index) {
  db_insert('example_search_index_update')
    ->fields(array(
      'index' => $index->machine_name,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_index')
    ->condition('index', $index->machine_name)
    ->execute();
}

/**
* Define default search indexes.
*
* @return array
*   An array of default search indexes, keyed by machine names.
*
* @see hook_default_search_api_index_alter()
*/
function hook_default_search_api_index() {
  $defaults['main'] = entity_create('search_api_index', array(
    'name' => 'Main index',
    'machine_name' => 'main',// Must be same as the used array key.
    // Other properties ...
  ));
  return $defaults;
}

/**
* Alter default search indexes.
*
* @param array $defaults
*   An array of default search indexes, keyed by machine names.
*
* @see hook_default_search_api_index()
*/
function hook_default_search_api_index_alter(array &$defaults) {
  $defaults['main']->name = 'Customized main index';
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * Convert a raw value from an entity to a custom data type.
 *
 * This function will be called for fields of the specific data type to convert
 * all individual values of the field to the correct format.
 *
 * @param $value
 *   The raw, single value, as extracted from an entity wrapper.
 * @param $original_type
 *   The original Entity API type of the value.
 * @param $type
 *   The custom data type to which the value should be converted. Can be ignored
 *   if the callback is only used for a single data type.
 *
 * @return
 *   The converted value, if a conversion could be executed. NULL otherwise.
 *
 * @see hook_search_api_data_type_info()
 */
function example_data_type_conversion($value, $original_type, $type) {
  if ($type === 'example_type') {
    // The example_type type apparently requires a rather complex data format.
    return array(
      'value' => $value,
      'original' => $original_type,
    );
  }
  // Someone used this callback for another, unknown type. Return NULL.
  // (Normally, you can just assume that the/a correct type is given.)
  return NULL;
}
