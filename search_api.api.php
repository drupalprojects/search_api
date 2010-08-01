<?php
// $Id$

/**
 * @file
 * Hooks provided by Drupal core and the System module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Defines one or more search service classes a module offers.
 *
 * Note: The ids should be valid PHP identifiers.
 *
 * @see hook_search_api_service_info_alter()
 *
 * @return array
 *   An associative array of search service classes, keyed by a unique
 *   identifier and containing associative arrays with the following keys:
 *   - name: The service class' translated name.
 *   - description: A translated string to be shown to administrators when
 *     selecting a service class.
 *   - class: The service class, which has to implement the
 *     SearchApiServiceInterface interface.
 */
function hook_search_api_service_info() {
  $services['example_some'] = array(
    'name' => t('Some Service'),
    'description' => t('Service for some search engine.'),
    'class' => 'SomeServiceClass',
    // Unknown keys can later be read by the object for additional information.
    'init args' => array('foo' => 'Foo', 'bar' => 42),
  );
  $services['example_other'] = array(
    'name' => t('Other Service'),
    'description' => t('Service for another search engine.'),
    'class' => 'OtherServiceClass',
  );

  return $services;
}

/**
 * Alter the Search API service info.
 *
 * Modules may implement this hook to alter the information that defines Search
 * API service. All properties that are available in
 * hook_search_api_service_info() can be altered here.
 *
 * @see hook_search_api_service_info()
 *
 * @param array $service_info
 *   The Search API service info array, keyed by service id.
 */
function hook_search_api_service_info_alter(array &$service_info) {
  foreach ($service_info as $id => $info) {
    $service_info[$id]['class'] = 'MyProxyServiceClass';
    $service_info[$id]['example_original_class'] = $info['class'];
  }
}

/**
 * Registers one or more callbacks that can be called at index time to add
 * additional data to the indexed items (e.g. comments or attachments to nodes),
 * alter the data in other forms or remove items from the array.
 *
 * For the required signature of callbacks, see example_random_alter().
 *
 * @see example_random_alter()
 *
 * @return array
 *   An associative array keyed by the function names and containing
 *   arrays with the following keys:
 *   - name: The name to display for this callback.
 *   - description: A short description of what the callback does.
 *   - weight: (optional) Defines the order in which callbacks are displayed
 *     (and, therefore, invoked) by default. Defaults to 0.
 */
function hook_search_api_alter_callback_info() {
  $callbacks['example_random_alter'] = array(
    'name' => t('Random alteration'),
    'description' => t('Alters all passed item data completely randomly.'),
    'weight' => 100,
  );
  $callbacks['example_add_comments'] = array(
    'name' => t('Add comments'),
    'description' => t('For nodes and similar entities, adds comments.'),
  );

  return $callbacks;
}

/**
 * Registers one or more processors. These are classes implementing the
 * SearchApiProcessorInterface interface which can be used at index and search
 * time to pre-process item data or the search query, and at search time to
 * post-process the returned search results.
 *
 * @see SearchApiProcessorInterface
 *
 * @return array
 *   An associative array keyed by the processor id and containing arrays
 *   with the following keys:
 *   - name: The name to display for this processor.
 *   - description: A short description of what the processor does at each
 *     phase.
 *   - class: The processor class, which has to implement the
 *     SearchApiProcessorInterface interface.
 *   - weight: (optional) Defines the order in which processors are displayed
 *     (and, therefore, invoked) by default. Defaults to 0.
 */
function hook_search_api_processor_info() {
  $callbacks['example_processor'] = array(
    'name' => t('Example processor'),
    'description' => t('Pre- and post-processes data in really cool ways.'),
    'class' => 'ExampleSearchApiProcessor',
    'weight' => -1,
  );
  $callbacks['example_processor_minimal'] = array(
    'name' => t('Example processor 2'),
    'description' => t('Processor with minimal description.'),
    'class' => 'ExampleSearchApiProcessor2',
  );

  return $callbacks;
}

/**
 * Lets modules alter a search query before executing it.
 *
 * @param SearchApiQuery $query
 *   The SearchApiQueryInterface object representing the search query.
 */
function hook_search_api_query_alter(SearchApiQueryInterface $query) {
  $info = entity_get_info($index->entity_type);
  $query->addCondition($info['entity keys']['id'], 0, '!=');
}

/**
 * Act on search servers when they are loaded.
 *
 * @param array $servers
 *   The loaded servers.
 */
function hook_search_api_server_load(array $servers) {
  foreach ($servers as $server) {
    db_insert('example_search_server_access')
      ->fields(array(
        'server' => $server->id,
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
      'server' => $server->id,
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * A search server was edited, enabled or disabled.
 *
 * @param SearchApiServer $server
 *   The edited server.
 * @param $op
 *   A hint as to how the server was updated. Either 'enable', 'disable' or
 *   'edit'.
 */
function hook_search_api_server_update(SearchApiServer $server, $op) {
  db_insert('example_search_server_update')
    ->fields(array(
      'server' => $server->id,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
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
      'server' => $server->id,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_server')
    ->condition('server', $server->id)
    ->execute();
}

/**
 * Act on search indexes when they are loaded.
 *
 * @param array $indexes
 *   The loaded indexes.
 */
function hook_search_api_index_load(array $indexes) {
  foreach ($indexes as $index) {
    db_insert('example_search_index_access')
      ->fields(array(
        'index' => $index->id,
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
      'index' => $index->id,
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * A search index was edited in any way.
 *
 * This includes being edited, enabled or disabled, as well as the index being
 * cleared or scheduled for re-indexing.
 *
 * @param SearchApiIndex $index
 *   The edited index.
 * @param $op
 *   A hint as to how the index was updated. Either 'enable', 'disable', 'edit',
 *   'reindex', 'clear' or 'fields'.
 */
function hook_search_api_index_update(SearchApiIndex $index, $op) {
  db_insert('example_search_index_update')
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
      'index' => $index->id,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_index')
    ->condition('index', $index->id)
    ->execute();
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * Search API data alteration callback that randomly changes item data.
 *
 * @param SearchApiIndex $index
 *   The index on which the items are indexed.
 * @param array $items
 *   An array of objects containing the entity data.
 *
 * @return array
 *   An array of property infos for all added properties, as required by
 *   hook_entity_property_info().
 */
function example_random_alter(SearchApiIndex $index, array &$items) {
  if ($index->id % 2) {
    foreach ($items as $id => $item) {
      srand($id);
      if (rand(0, 4)) {
        unset($items[$id]);
        continue;
      }
      foreach ($item as $k => $v) {
        srand(drupal_strlen($v) + count($v));
        $item->$k = rand(1, 100);
      }
    }
  }

  foreach ($items as $id => $item) {
    $item->example_id_plus_rand = "$id-" . rand(1, 500);
  }

  return array(
    'example-id-plus-rand' => array(
      'label' => t('Useless field'),
      'description' => t('A really, really useless field, consisting of the entity ID and a random number.'),
      'type' => 'text',
    )
  );
}
