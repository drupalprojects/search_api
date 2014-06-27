<?php

/**
 * @file
 * Drush commands for SearchAPI.
 *
 * Original file by agentrickard for Palantir.net
 */

/**
 * In the unlikely event that there is an index by this name
 * then drush commands operating on all indexes will be 
 * unpredicatable
 */
define('ALL_INDEXES', '_SEARCH_API_ALL_INDEXES_1296_98222');


/**
 * Implements hook_drush_command().
 */
function search_api_drush_command() {
  $items = array();

  $items['search-api-list'] = array(
    'description' => 'List all search indexes.',
    'examples' => array(
      'drush searchapi-list' => dt('List all search indexes.'),
      'drush sapi-l' => dt('Alias to list all search indexes.'),
    ),
    'aliases' => array('sapi-l'),
  );

  $items['search-api-enable'] = array(
    'description' => 'Enable one or more disabled search_api indexes.',
    'examples' => array(
      'drush sapi-en' => dt('Alias to enable disabled indexes.'),
      'drush sapi-en default_node_index' => dt('Enable index with the machine name !name.', array('!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index to enable.'),
    ),
    'aliases' => array('sapi-en'),
  );

  $items['search-api-enable-all'] = array(
    'description' => 'Enable all disabled search_api indexes.',
    'examples' => array(
      'drush searchapi-enable' => dt('Enable all disabled indexes.'),
      'drush sapi-en' => dt('Alias to enable all disabled indexes.'),
    ),
    'arguments' => array(
    ),
    'aliases' => array('sapi-ena'),
  );

  $items['search-api-disable'] = array(
    'description' => 'Disable one or more enabled search_api indexes.',
    'examples' => array(
      'drush sapi-dis' => dt('Alias to disable enabled indexes.'),
      'drush sapi-dis 1' => dt('Disable index with the machine name !name.', array('!name' => 1)),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index to disable.'),
    ),
    'aliases' => array('sapi-dis'),
  );

  $items['search-api-disable-all'] = array(
    'description' => 'Disable all enabled search_api indexes.',
    'examples' => array(
      'drush sapi-dis' => dt('Alias to disable all enabled indexes.'),
    ),
    'arguments' => array(
    ),
    'aliases' => array('sapi-disa'),
  );

  $items['search-api-status'] = array(
    'description' => 'Show the status of one or all search indexes.',
    'examples' => array(
      'drush searchapi-status' => dt('Show the status of all search indexes.'),
      'drush sapi-s' => dt('Alias to show the status of all search indexes.'),
      'drush sapi-s 1' => dt('Show the status of the search index with the name !name.', array('!name' => 1)),
      'drush sapi-s default_node_index' => dt('Show the status of the search index with the machine name !name.', array('!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index to view status.'),
    ),
    'aliases' => array('sapi-s'),
  );

  $items['search-api-index'] = array(
    'description' => 'Index items for one or all enabled search_api indexes.',
    'examples' => array(
      'drush searchapi-index' => dt('Index items for all enabled indexes.'),
      'drush sapi-i' => dt('Alias to index items for all enabled indexes.'),
      'drush sapi-i default_node_index' => dt('Index items for the index with the machine name !name.', array('!name' => 'default_node_index')),
      'drush sapi-i default_node_index 100' => dt("Index a maximum number of !limit items (index's cron batch size items per batch run) for the index with the machine name !name.", array('!limit' => 100, '!name' => 'default_node_index')),
      'drush sapi-i default_node_index 100 10' => dt("Index a maximum number of !limit items (!batch_size items per batch run) for the index with the machine name !name.", array('!limit' => 100, '!batch_size' => 10, '!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index.'),
      'limit' => dt("The number of items to index (index's cron batch size items per run). Set to 0 to index all items. Defaults to 0 (index all)."),
      'batch_size' => dt("The number of items to index per batch run. Set to 0 to index all items at once. Defaults to the index's cron batch size."),
    ),
    'aliases' => array('sapi-i'),
  );

  $items['search-api-reindex'] = array(
    'description' => 'Force reindexing of one or all search indexes, without clearing existing index data.',
    'examples' => array(
      'drush searchapi-reindex' => dt('Schedule all search indexes for reindexing.'),
      'drush sapi-r' => dt('Alias to schedule all search indexes for reindexing .'),
      'drush sapi-r default_node_index' => dt('Schedule the search index with the machine name !name for re-indexing.', array('!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index.'),
    ),
    'aliases' => array('sapi-r'),
  );

  $items['search-api-clear'] = array(
    'description' => 'Clear one or all search indexes and mark them for re-indexing.',
    'examples' => array(
      'drush searchapi-clear' => dt('Clear all search indexes.'),
      'drush sapi-c' => dt('Alias to clear all search indexes.'),
      'drush sapi-c default_node_index' => dt('Clear the search index with the machine name !name.', array('!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index.'),
    ),
    'aliases' => array('sapi-c'),
  );

  return $items;
}



/**
 * List all search indexes.
 */
function drush_search_api_list() {
  $indexes = entity_load_multiple('search_api_index');
  if (empty($indexes)) {
    drush_print(dt('There are no indexes present.'));
    return;
  }
  $rows[] = array(
    dt('Machine name'),
    dt('Name'),
    dt('Server'),
    dt('Type'),
    dt('Status'),
    dt('Limit'),
  );
  /** @var $index \Drupal\search_api\Entity\Index */
  foreach ($indexes as $index) {
    foreach ($index->getDatasourceIds() as $datasource_id) {
      // Check if a datasource is available.
      if ($index->isValidDatasource($datasource_id)) {
        // Use the label defined in the plugin definition.
        $info = $index->getDatasource($datasource_id)->getPluginDefinition()['label'];
      }
      else {
        $info = t('Invalid or missing datasource plugin: %datasource_id', array('%datasource_id' => $datasource_id));
      }
    }
    $row = array(
      $index->machine_name,
      $index->name,
      $index->getServerId() ?: '(' . t('none') . ')',
      (string) $info,
      $index->status() ? t('enabled') : t('disabled'),
      (int) $index->getOption('cron_limit'),
    );
    $rows[] = $row;
  }
  drush_print_table($rows);
}

/**
 * Enable index(es).
 *
 * @param string $index_machine_name
 *   Machine name for index to be enabled.
 */
function drush_search_api_enable() {
  if (!search_api_drush_get_index_count()) {
    drush_set_error(dt('There are no indexes defined. Please define an index before trying to enable it'));
    return;
  }

  $index_names = func_get_args();

  if (!count($index_names)) {
    drush_set_error(dt('You must specify at least one index to enable. The indexes I know about are:'));
    drush_search_api_list();
  }

  foreach ($index_names as $index_name) {
    $indexes = search_api_drush_get_indexes_or_display_list($index_name);
    if (empty($indexes)) {
      continue;
    }
    foreach ($indexes as $index) {
      _drush_search_api_set_index_state($index, TRUE);
    }
  }
}

/**
 * Enable all index(es).
 *
 */
function drush_search_api_enable_all() {
  drush_search_api_enable(SEARCH_API_ALL_INDEXES);
}

/**
 * Disable index(es).
 *
 */
function drush_search_api_disable() {
  if (!search_api_drush_get_index_count()) {
    drush_set_error(dt('There are no indexes defined. Please define an index before trying to disable it'));
    return;
  }

  $index_names = func_get_args();

  if (!count($index_names)) {
    drush_set_error(dt('You must specify at least one index to disable. The indexes I know about are:'));
    drush_search_api_list();
  }

  foreach ($index_names as $index_name) {
    $indexes = search_api_drush_get_indexes_or_display_list($index_name);
    if (empty($indexes)) {
      continue;
    }
    foreach ($indexes as $index) {
      _drush_search_api_set_index_state($index, FALSE);
    }
  }
}

/**
 * Disable all index(es).
 *
 */
function drush_search_api_disable_all() {
  drush_search_api_disable(SEARCH_API_ALL_INDEXES);
}

/**
 * Display index status.
 */
function drush_search_api_status($index_name = NULL) {
  $indexes = search_api_drush_get_indexes_or_display_list($index_name);
  if (empty($indexes)) {
    return;
  }
  // See search_api_index_status()
  $rows = array(array(
    dt('Machine name'),
    dt('Name'),
    dt('% Complete'),
    dt('Indexed'),
    dt('Total'),
  ));
  foreach ($indexes as $index) {
    /* @var $index \Drupal\search_api\Entity\Index */
    $complete = ($index->getTracker()->getTotalItemsCount() > 0) ?
      100 * round($index->getTracker()->getIndexedItemsCount() / $index->getTracker()->getTotalItemsCount(), 3) . '%' : '-';
    $row = array(
      $index->machine_name,
      $index->name,
      $complete,
      $index->getTracker()->getIndexedItemsCount(),
      $index->getTracker()->getTotalItemsCount(),
    );
    $rows[] = $row;
  }
  drush_print_table($rows);
}

/**
 * Index items.
 * @todo: need to make real invoke
 *
 * @param string $index_name
 *   The index machine name for which items should be indexed.
 * @param integer $limit
 *   Maximum number of items to index.
 * @param integer $batch_size
 *   Number of items to index per batch.
 */
function drush_search_api_index($index_name = NULL, $limit = NULL, $batch_size = NULL) {
  $indexes = search_api_drush_get_indexes_or_display_list($index_name);
  if (empty($indexes)) {
    return;
  }
  foreach ($indexes as $index) {
    /* @var $index \Drupal\search_api\Entity\Index */
    $tracker = $index->getTracker();
    $remaining = $tracker->getTotalItemsCount() - $tracker->getIndexedItemsCount();

    if ($remaining <= 0) {
      drush_log(dt("The index !index is up to date.", array('!index' => $index->name)), 'ok');
      continue;
    }
    else {
      $t_args = array(
        '!remaining' => $remaining,
        '!limit' => ($limit) ? $limit : t('All'),
        '!index' => $index->name,
      );
      drush_log(dt("The index found !remaining items to index for !index. Indexing !limit items.", $t_args), 'ok');
    }

    $index->index($limit);

    // Get the number of items to index per batch run.
    /*if (!isset($batch_size)) {
      $cron_limit = $index->getOption('cron_limit');
      $searchApiSettings = \Drupal::configFactory()->get('search_api.settings');
      $batch_size = empty($cron_limit) ?: $searchApiSettings->get('cron_limit');
    }
    elseif ($batch_size <= 0) {
      $batch_size = $remaining;
    }

    // Get the number items to index.
    if (!isset($limit) || !is_int($limit += 0) || $limit <= 0) {
      $limit = $remaining;
    }

    drush_log(dt("Indexing a maximum number of !limit items (!batch_size items per batch run) for the index !index.", array('!index' => $index->name, '!limit' => $limit, '!batch_size' => $batch_size)), 'ok');

    drush_log("@todo: INITIATING BATCH IS NOT YET IMPLEMENTED.", 'error');
    return;

    // Create the batch.
    if (!_search_api_batch_indexing_create($index, $batch_size, $limit, $remaining, TRUE)) {
      drush_log(dt("Couldn't create a batch, please check the batch size and limit parameters."), 'error');
    }
    else {
      // Launch the batch process.
      drush_backend_batch_process();
    }*/
  }
}

/**
 * Mark for re-indexing.
 *
 * @param string $index_name
 *   The index machine name for which items should be reindexed.
 */
function drush_search_api_reindex($index_name = NULL) {
  $indexes = search_api_drush_get_indexes_or_display_list($index_name);
  if (empty($indexes)) {
    return;
  }
  // See search_api_index_reindex()
  foreach ($indexes as $index) {
    $index->reindex();
    drush_log(dt('!index was successfully marked for re-indexing.', array('!index' => $index->machine_name)), 'ok');
  }
}

/**
 * Clear an index.
 *
 * @param string $index_name
 *   The index machine name for clearing.
 */
function drush_search_api_clear($index_name = NULL) {
  $indexes = search_api_drush_get_indexes_or_display_list($index_name);
  if (empty($indexes)) {
    return;
  }
  // See search_api_index_reindex()
  foreach ($indexes as $index) {
    $index->clear();
    drush_log(dt('!index was successfully cleared.', array('!index' => $index->machine_name)), 'ok');
  }
}

/**
 * search_api_drush_get_index_count 
 * Helper function to return the number of indexes
 * 
 * @return integer
 */
function search_api_drush_get_index_count() {
    return count(entity_load_multiple('search_api_index'));
}

/**
 * Helper function to return an index or all indexes as an array.
 * If the index is not defined or there are no indexes then a friendly message
 * is displayed
 *
 * @param $index_name
 *   The provided index id or the constant SEARCH_API_ALL_INDEXES to return all indexes
 *
 * @return
 *   An array of indexes.
 */
function search_api_drush_get_indexes_or_display_list($index_name) {
  if ($index_name == SEARCH_API_ALL_INDEXES) {
    $indexes = entity_load_multiple('search_api_index');
  }
  else {
    $indexes = entity_load_multiple_by_properties('search_api_index', array('machine_name' => $index_name));
  }

  //found no indexes, display a nice message
  if (empty($indexes)) {
    if ($index_name == SEARCH_API_ALL_INDEXES) {
      drush_set_error(dt('No indexes present.'));
    }
    else {
      drush_set_error(dt('Invalid index machine name "!index_name" or no indexes present. The following indexes are defined:', array('!index_name' => $index_name)));
      drush_print();
      drush_search_api_list();
    }
  }
  return $indexes;
}



/**
 * Helper function to change the state of a single index.
 * Displays helpful messages if the state cannot be changed
 *
 * @param string $index_machine_name
 *   Machine name for index to be enabled.
 *
 * @param boolean $enable
 *   TRUE to enable, FALSE to disable the index
 */
function _drush_search_api_set_index_state($index, $enable = TRUE) {

  $desired_state_name = $enable ? dt('enabled') : dt('disabled');
  $desired_state_setter_method = $enable ? 'enable' : 'disable';

  /* @var $index \Drupal\search_api\Entity\Index */
  if ($index->status() == $enable) {
    drush_log(dt("The index !index is already !desired_state_name.", array('!index' => $index->machine_name, '!desired_state_name' => $desired_state_name)), 'ok');
    return;
  }
  if (!$index->getServerId()) {
    drush_log(dt("Cannot set index !index to !desired_state_name because it is not bound to any server.", array('!index' => $index->machine_name, '!desired_state_name' => $desired_state_name)), 'warning');
    return;
  }

  if ($index->$desired_state_setter_method()->save() == SAVED_UPDATED) {
    drush_log(dt("The index !index was successfully !desired_state_name.", array('!index' => $index->machine_name, '!desired_state_name' => $desired_state_name)), 'ok');
  }
  else {
    drush_log(dt("Error setting state of index !index to !desired_state_name ", array('!index' => $index->machine_name, '!desired_state_name' => $desired_state_name)), 'error');
  }

}
