<?php

/**
 * @file
 * Drush commands for SearchAPI.
 *
 * Original file by agentrickard for Palantir.net
 */

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
    'description' => 'Enable one or all disabled search_api indexes.',
    'examples' => array(
      'drush searchapi-enable' => dt('Enable all disabled indexes.'),
      'drush sapi-en' => dt('Alias to enable all disabled indexes.'),
      'drush sapi-en default_node_index' => dt('Enable index with the machine name !name.', array('!name' => 'default_node_index')),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index to enable.'),
    ),
    'aliases' => array('sapi-en'),
  );

  $items['search-api-disable'] = array(
    'description' => 'Disable one or all enabled search_api indexes.',
    'examples' => array(
      'drush searchapi-disable' => dt('Disable all enabled indexes.'),
      'drush sapi-dis' => dt('Alias to disable all enabled indexes.'),
      'drush sapi-dis 1' => dt('Disable index with the machine name !name.', array('!name' => 1)),
    ),
    'arguments' => array(
      'index_name' => dt('The machine name of an index to disable.'),
    ),
    'aliases' => array('sapi-dis'),
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
    $plugin_info = \Drupal::service('plugin.manager.search_api.datasource')->getDefinition($index->datasourcePluginId);
    $row = array(
      $index->machine_name,
      $index->name,
      $index->getServerId() ?: '(' . t('none') . ')',
      $plugin_info['label'],
      $index->status() ? t('enabled') : t('disabled'),
      $index->getOption('cron_limit'),
    );
    $rows[] = $row;
  }
  drush_print_table($rows);
}

/**
 * Enable index(es).
 *
 * @param string $index_machine_name
 *   Optional machine name for index to be enabled. If absent - enable all.
 */
function drush_search_api_enable($index_name = NULL) {
  $indexes = search_api_drush_get_index($index_name);
  if (empty($indexes)) {
    return;
  }
  foreach ($indexes as $index) {
    /* @var $index \Drupal\search_api\Entity\Index */
    if ($index->status()) {
      drush_log(dt("The index !index is already enabled.", array('!index' => $index->machine_name)), 'ok');
      continue;
    }
    if (!$index->getServerId()) {
      drush_log(dt("Cannot enable index !index because it is not bound to any server.", array('!index' => $index->machine_name)), 'warning');
      continue;
    }

    drush_log(dt("Enabling index !index and queueing items for indexing.", array('!index' => $index->machine_name)), 'notice');
    if ($index->enable()->save() == SAVED_UPDATED) {
      drush_log(dt("The index !index was successfully enabled.", array('!index' => $index->machine_name)), 'ok');
    }
    else {
      drush_log(dt("Error enabling index !index.", array('!index' => $index->machine_name)), 'error');
    }
  }
}

/**
 * Disable index(es).
 *
 * @param string $index_machine_name
 *   Optional machine name for index to be disabled. If absent - disable all.
 */
function drush_search_api_disable($index_name = NULL) {
  $indexes = search_api_drush_get_index($index_name);
  if (empty($indexes)) {
    return;
  }
  foreach ($indexes as $index) {
    /* @var $index \Drupal\search_api\Entity\Index */
    if (!$index->status()) {
      drush_log(dt("The index !index is already disabled.", array('!index' => $index->machine_name)), 'ok');
      continue;
    }

    if ($index->disable()->save() == SAVED_UPDATED) {
      drush_log(dt("The index !index was successfully disabled.", array('!index' => $index->machine_name)), 'ok');
    }
    else {
      drush_log(dt("Error disabling index !index.", array('!index' => $index->machine_name)), 'error');
    }
  }
}

/**
 * Display index status.
 */
function drush_search_api_status($index_name = NULL) {
  $indexes = search_api_drush_get_index($index_name);
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
    $complete = ($index->getDatasource()->getTotalItemsCount() > 0) ?
      100 * round($index->getDatasource()->getIndexedItemsCount() / $index->getDatasource()->getTotalItemsCount(), 3) . '%' : '-';
    $row = array(
      $index->machine_name,
      $index->name,
      $complete,
      $index->getDatasource()->getIndexedItemsCount(),
      $index->getDatasource()->getTotalItemsCount(),
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
  $indexes = search_api_drush_get_index($index_name);
  if (empty($indexes)) {
    return;
  }
  foreach ($indexes as $index) {
    /* @var $index \Drupal\search_api\Entity\Index */
    $data_source = $index->getDatasource();
    $remaining = $data_source->getTotalItemsCount() - $data_source->getIndexedItemsCount();

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
  $indexes = search_api_drush_get_index($index_name);
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
  $indexes = search_api_drush_get_index($index_name);
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
 * Helper function to return an index or all indexes as an array.
 *
 * @param $index_name
 *   (optional) The provided index id.
 *
 * @return
 *   An array of indexes.
 */
function search_api_drush_get_index($index_name = NULL) {
  if ($index_name) {
    $indexes = entity_load_multiple_by_properties('search_api_index', array('machine_name' => $index_name));
  }
  else {
    $indexes = entity_load_multiple('search_api_index');
  }
  if (empty($indexes)) {
    drush_set_error(dt('Invalid index machine name or no indexes present. Listing all indexes:'));
    drush_print();
    drush_search_api_list();
  }
  return $indexes;
}
