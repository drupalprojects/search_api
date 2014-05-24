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
 * Alter the available Search API backends.
 *
 * Modules may implement this hook to alter the information that defines Search
 * API backends. All properties that are available in
 * \Drupal\search_api\Annotation\SearchApiBackend can be altered here, with the
 * addition of the "class" and "provider" keys.
 *
 * @param array $backend_info
 *   The Search API backend info array, keyed by backend ID.
 *
 * @see \Drupal\search_api\Backend\BackendPluginBase
 */
function hook_search_api_backend_info_alter(array &$backend_info) {
  foreach ($backend_info as $id => $info) {
    $backend_info[$id]['class'] = '\Drupal\my_module\MyBackendDecorator';
    $backend_info[$id]['example_original_class'] = $info['class'];
  }
}

/**
 * Alter the available datasources.
 *
 * Modules may implement this hook to alter the information that defines
 * datasources and item types. All properties that are available in
 * \Drupal\search_api\Annotation\SearchApiDatasource can be altered here, with
 * the addition of the "class" and "provider" keys.
 *
 * @param array $infos
 *   The datasource info array, keyed by type identifier.
 *
 * @see \Drupal\search_api\Datasource\DatasourcePluginBase
 */
function hook_search_api_datasource_info_alter(array &$infos) {
  // I'm a traditionalist, I want them called "nodes"!
  $infos['entity:node']['label'] = t('Node');
}


/**
 * Alter the available processors.
 *
 * Modules may implement this hook to alter the information that defines
 * processors. All properties that are available in
 * \Drupal\search_api\Annotation\SearchApiProcessor can be altered here, with
 * the addition of the "class" and "provider" keys.
 *
 * @param array $processors
 *   The processor information to be altered, keyed by processor IDs.
 *
 * @see \Drupal\search_api\Processor\ProcessorPluginBase
 */
function hook_search_api_processor_info_alter(array &$processors) {
  if (!empty($processors['example_processor'])) {
    $processors['example_processor']['class'] = '\Drupal\my_module\MuchBetterExampleProcessor';
  }
}

/**
 * Alter the mapping of Drupal data types to Search API data types.
 *
 * @param array $mapping
 *   An array mapping all known (and supported) Drupal data types to their
 *   corresponding Search API data types. Empty values mean that fields of
 *   that type should be ignored by the Search API.
 *
 * @see \Drupal\search_api\Utility\Utility::getFieldTypeMapping()
 */
function hook_search_api_field_type_mapping_alter(array &$mapping) {
  $mapping['duration_iso8601'] = NULL;
  $mapping['my_new_type'] = 'string';
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
 *   The items that will be indexed, in the format specified by
 *   \Drupal\search_api\Backend\BackendSpecificInterface::indexItems().
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The search index on which items will be indexed.
 */
function hook_search_api_index_items_alter(array &$items, \Drupal\search_api\Index\IndexInterface $index) {
  foreach ($items as $id => $item) {
    if ($id % 5 == 0) {
      unset($items[$id]);
    }
  }
  drupal_set_message(t('Indexing @type items with the following IDs: @ids', array('@type' => $index->getDatasourceId(), '@ids' => implode(', ', array_keys($items)))));
}

/**
 * Allows modules to react after items were indexed.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The used index.
 * @param array $item_ids
 *   An array containing the successfully indexed items' IDs.
 */
function hook_search_api_items_indexed(\Drupal\search_api\Index\IndexInterface $index, array $item_ids) {
  if ($index->isValidDatasource('entity:node')) {
    drupal_set_message(t('Nodes indexed: @ids.', implode(', ', $item_ids)));
  }
}

/**
 * Lets modules alter a search query before executing it.
 *
 * @param \Drupal\search_api\Query\QueryInterface $query
 *   The query that will be executed.
 */
function hook_search_api_query_alter(\Drupal\search_api\Query\QueryInterface $query) {
  // Exclude entities with ID 0. (Assume the ID field is always indexed.)
  $types = $query->getIndex()->getDatasourceIds();
  foreach ($types as $type) {
    list(, $type) = explode(':', $type);
    $definition = \Drupal::entityManager()->getDefinition($type, FALSE);
    if ($definition) {
      $keys = $definition->getKeys();
      $query->condition($keys['id'], 0, '!=');
    }
  }
}

/**
 * Act on search servers when they are loaded.
 *
 * @param \Drupal\search_api\Server\ServerInterface[] $servers
 *   An array of loaded server objects.
 */
function hook_search_api_server_load(array $servers) {
  foreach ($servers as $server) {
    db_insert('example_search_server_access')
      ->fields(array(
        'server' => $server->id(),
        'access_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * Respond to the creation of a server.
 *
 * @param \Drupal\search_api\Server\ServerInterface $server
 *   The new server.
 */
function hook_search_api_server_insert(\Drupal\search_api\Server\ServerInterface $server) {
  db_insert('example_search_server')
    ->fields(array(
      'server' => $server->id(),
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * Act on a search server being inserted or updated.
 *
 * This hook is invoked from $server->save() before the server is saved to the
 * database.
 *
 * @param \Drupal\search_api\Server\ServerInterface $server
 *   The search server that is being inserted or updated.
 */
function hook_search_api_server_presave(\Drupal\search_api\Server\ServerInterface $server) {
  // We don't want people to be able to disable servers.
  $server->setStatus(TRUE);
}

/**
 * Respond to updates to a server.
 *
 * @param \Drupal\search_api\Server\ServerInterface $server
 *   The edited server.
 */
function hook_search_api_server_update(\Drupal\search_api\Server\ServerInterface $server) {
  if ($server->name != $server->original->name) {
    db_insert('example_search_server_name_update')
      ->fields(array(
        'server' => $server->id(),
        'update_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * Respond to the deletion of a server.
 *
 * @param \Drupal\search_api\Server\ServerInterface $server
 *   The deleted server.
 */
function hook_search_api_server_delete(\Drupal\search_api\Server\ServerInterface $server) {
  db_insert('example_search_server_update')
    ->fields(array(
      'server' => $server->id(),
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_server')
    ->condition('server', $server->id())
    ->execute();
}

/**
 * Act on search indexes when they are loaded.
 *
 * @param \Drupal\search_api\Index\IndexInterface[] $indexes
 *   An array of loaded index objects.
 */
function hook_search_api_index_load(array $indexes) {
  foreach ($indexes as $index) {
    db_insert('example_search_index_access')
      ->fields(array(
        'index' => $index->id(),
        'access_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * Respond to the creation of a index.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The new index.
 */
function hook_search_api_index_insert(\Drupal\search_api\Index\IndexInterface $index) {
  db_insert('example_search_index')
    ->fields(array(
      'index' => $index->id(),
      'insert_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * Act on a search index being inserted or updated.
 *
 * This hook is invoked from $index->save() before the index is saved to the
 * database.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The search index that is being inserted or updated.
 */
function hook_search_api_index_presave(\Drupal\search_api\Index\IndexInterface $index) {
  // We don't want people to be able to disable indexes.
  $index->setStatus(TRUE);
}

/**
 * Respond to updates to a index.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The edited index.
 */
function hook_search_api_index_update(\Drupal\search_api\Index\IndexInterface $index) {
  if ($index->name != $index->original->name) {
    db_insert('example_search_index_name_update')
      ->fields(array(
        'index' => $index->id(),
        'update_time' => REQUEST_TIME,
      ))
      ->execute();
  }
}

/**
 * Respond to the deletion of a index.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The deleted index.
 */
function hook_search_api_index_delete(\Drupal\search_api\Index\IndexInterface $index) {
  db_insert('example_search_index_update')
    ->fields(array(
      'index' => $index->id(),
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
  db_delete('example_search_index')
    ->condition('index', $index->id())
    ->execute();
}

/**
 * A search index was scheduled for reindexing
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The edited index.
 * @param $clear
 *   Boolean indicating whether the index was also cleared.
 */
function hook_search_api_index_reindex(\Drupal\search_api\Index\IndexInterface $index, $clear = FALSE) {
  db_insert('example_search_index_reindexed')
    ->fields(array(
      'index' => $index->id(),
      'clear' => $clear,
      'update_time' => REQUEST_TIME,
    ))
    ->execute();
}

/**
 * Alter the query before executing the query.
 *
 * @param \Drupal\views\Entity\View $view
 *   The view object about to be processed.
 * @param \Drupal\search_api\Query\QueryInterface $query
 *   The Search API Views query to be altered.
 *
 * @see hook_views_query_alter()
 */
function hook_search_api_views_query_alter(\Drupal\views\Entity\View &$view, Drupal\search_api\Query\QueryInterface &$query) {
  // (Example assuming a view with an exposed filter on node title.)
  // If the input for the title filter is a positive integer, filter against
  // node ID instead of node title.
  if ($view->name == 'my_view' && is_numeric($view->exposed_raw_input['title']) && $view->exposed_raw_input['title'] > 0) {
    // Traverse through the 'where' part of the query.
    foreach ($query->where as &$condition_group) {
      foreach ($condition_group['conditions'] as &$condition) {
        // If this is the part of the query filtering on title, chang the
        // condition to filter on node ID.
        if (reset($condition) == 'node.title') {
          $condition = array('node.nid', $view->exposed_raw_input['title'],'=');
        }
      }
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
