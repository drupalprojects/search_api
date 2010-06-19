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
 * @return An associative array of search service classes, keyed by a unique
 *   identifier and containing associative arrays with the following keys:
 *   - name: The service class' translated name.
 *   - description: A translated string to be shown to administrators when
 *     selecting a service class.
 *   - class: The service class, which has to implement the
 *     SearchApiServiceInterface interface.
 *   - 'init args': (optional) Array of arguments to be passed to the service
 *     object's init() method after its creation. Empty by default.
 */
function hook_search_api_service_info() {
  $services['example_some'] = array(
    'name' => t('Some Service'),
    'description' => t('Service for some search engine.'),
    'class' => 'SomeServiceClass',
    'init args' => array('foo' => 'Foo', 'bar' => 42),
  );
  $services['example_other'] = array(
    'name' => t('Other Service'),
    'description' => t('Service for another search engine.'),
    'class' => 'OtherServiceClass',
    // 'init args' => array(), // implicit
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
 * @param $service_info
 *   The Search API service info array, keyed by service id.
 */
function hook_search_api_service_info_alter(&$service_info) {

}

/**
 * Registers one or more callbacks that can be called at index time to add
 * additional data to the indexed items (e.g. comments or attachments to nodes),
 * alter the data in other forms or remove items from the array.
 *
 * For the required signature of callbacks, see example_random_alter().
 *
 * @return An associative array keyed by the function names and containing
 *   arrays with the following keys:
 *   - name: The name to display for this callback.
 *   - description: A short description of what the callback does.
 *   - enabled: (optional) Whether this callback should be enabled by default.
 *     Defaults to TRUE.
 *   - weight: (optional) Defines the order in which callbacks are displayed
 *     (and, therefore, invoked) by default. Defaults to 0.
 *
 * @see example_random_alter
 */
function hook_search_api_register_alter_callback() {
  $callbacks['example_random_alter'] = array(
    'name' => t('Random alteration'),
    'description' => t('Alters all passed item data completely randomly.'),
    'enabled' => FALSE,
    'weight' => 100,
  );
  $callbacks['example_add_comments'] = array(
    'name' => t('Add comments'),
    'description' => t('For nodes and similar entities, adds comments.'),
  );

  return $callbacks;
}

/**
 * @} End of "addtogroup hooks".
 */

/**
 * Search API data alteration callback that randomly changes item data.
 *
 * @param $index The index on which the items are indexed.
 * @param $items An array of objects containing the entity data.
 */
function example_random_alter($index, &$items) {
  if ($index->id % 2) {
    foreach ($items as $id => $item) {
      srand($id);
      if (rand() % 5) {
        unset($items[$id]);
        continue;
      }
      foreach ($item as $k => $v) {
        srand(strlen($v) + count($v));
        $item->$k = rand();
      }
    }
  }
}
