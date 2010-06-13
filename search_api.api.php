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
 * Defines one or more search backends a module offers.
 *
 * @return An associative array of search backends, keyed by a unique identifier
 *   (usually the class name) and containing associative arrays with the
 *   following keys:
 *   - name: The backend's translated name.
 *   - description: A translated string to be shown to administrators when
 *     selecting a backend.
 *   - class: (optional) The backend class, which has to implement the
 *     SearchApiBackend interface. Defaults to the identifier used as the array
 *     key.
 *   - 'init args': (optional) Array of arguments to be passed to the backend
 *     object's init() method after its creation. Empty by default.
 */
function hook_search_api_backends() {
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
 * @} End of "addtogroup hooks".
 */
