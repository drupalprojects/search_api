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
 * Defines one or more search backend classes a module offers.
 *
 * @return An associative array of search backend classes, keyed by a unique
 *   identifier (usually the class name) and containing associative arrays with
 *   the following keys:
 *   - name: The backend class' translated name.
 *   - description: A translated string to be shown to administrators when
 *     selecting a backend class.
 *   - class: (optional) The backend class, which has to implement the
 *     SearchApiBackend interface. Defaults to the identifier used as the array
 *     key.
 *   - 'init args': (optional) Array of arguments to be passed to the backend
 *     object's init() method after its creation. Empty by default.
 */
function hook_search_api_backend_classes() {
  $backends['SomeBackendClass1'] = array(
    'name' => t('Some Backend'),
    'description' => t('Backend for some search engine.'),
    'class' => 'SomeBackendClass',
    'init args' => array('foo' => 'Foo', 'bar' => 42),
  );
  $backends['OtherBackendClass'] = array(
    'name' => t('Other Backend'),
    'description' => t('Backend for another search engine.'),
    // 'class' => 'OtherBackendClass', // implicit
    // 'init args' => array(), // implicit
  );

  return $backends;
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

/**
 * @} End of "addtogroup hooks".
 */
