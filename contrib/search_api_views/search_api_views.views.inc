<?php
// $Id$

/**
 * Implements hook_views_data().
 */
function search_api_views_views_data() {
  $data = array();
  foreach (search_api_index_load_multiple(FALSE) as $index) {
    // Base data
    $key = 'search_api_index_' . $index->machine_name;
    $table = &$data[$key];
    $entity_info = entity_get_info($index->entity_type);
    $table['table']['group'] = $entity_info['label'];
    $table['table']['base'] = array(
      'field' => 'search_api_id',
      'index' => $index->machine_name,
      'title' => $index->name,
      'help' => t('Use the %name search index for filtering and retrieving data.', array('%name' => $index->name)),
      'query class' => 'search_api_views_query',
    );

    // Add all available fields
    // This is largely copied from _search_api_admin_get_fields().
    $max_depth = variable_get('search_api_views_max_fields_depth', 2);
    $orig_wrapper = $index->entityWrapper();
    $fields = empty($index->options['fields']) ? array() : $index->options['fields'];

    // A wrapper for a specific field name prefix, e.g. 'user:' mapped to the user wrapper
    $wrappers = array('' => $orig_wrapper);
    // Display names for the prefixes
    $prefix_names = array('' => '');
    // The list nesting level for entities with a certain prefix
    $nesting_levels = array('' => 0);

    $types = search_api_field_types();
    $types['options'] = t('Options');
    while ($wrappers) {
      foreach ($wrappers as $prefix => $wrapper) {
        $prefix_name = $prefix_names[$prefix];
        $depth = substr_count($prefix, ':');
        // Deal with lists of entities.
        $nesting_level = $nesting_levels[$prefix];
        $type_prefix = str_repeat('list<', $nesting_level);
        $type_suffix = str_repeat('>', $nesting_level);
        if ($nesting_level) {
          $info = $wrapper->info();
          // The real nesting level of the wrapper, not the accumulated one.
          $level = search_api_list_nesting_level($info['type']);
          for ($i = 0; $i < $level; ++$i) {
            $wrapper = $wrapper[0];
          }
        }
        // Now look at all properties.
        foreach ($wrapper as $property => $value) {
          $info = $value->info();
          $type = $type_prefix . $info['type'] . $type_suffix;
          $inner_type = search_api_extract_inner_type($info['type']);
          if ($inner_type == 'token') {
            $inner_type = 'string';
            $type = search_api_nest_type('string', $type);
          }
          $key = $prefix . $property;
          if ($key == 'table') {
            // We can't add a "table" field since that would overwrite the table
            // information. For the moment, we just don't add such a field.
            continue;
          }
          if (isset($types[$inner_type])) {
            if ($value->optionsList()) {
              $inner_type = 'options';
              $type = search_api_nest_type('options', $type);
            }
            // Add field handler.
            if ($prefix_name) {
              $table[$key]['group'] = $prefix_name;
            }
            $table[$key]['title'] = $info['label'];
            $table[$key]['help'] = empty($info['description']) ? t('(No information available)') : $info['description'];
            $table[$key]['type'] = $type;
            $table[$key]['field']['handler'] = _search_api_views_field_handler($type, $inner_type);
            if ($inner_type == 'options') {
              $table[$key]['field']['options'] = $value->optionsList();
            }

            // If field is indexed, also add additional handlers.
            if (!empty($fields[$key]['indexed'])) {
              // Discern between original and indexed type
              $table[$key]['field']['type'] = $table[$key]['type'];
              $table[$key]['type'] = $fields[$key]['type'];
              $table[$key] += _search_api_views_add_handlers($fields[$key], $value);
              if (!empty($table[$key]['sort'])) {
                $table[$key]['field']['click sortable'] = TRUE;
              }
            }
            unset($fields[$key]);
          }
          elseif ($depth < $max_depth) {
            // Visit this entity/struct in a later iteration.
            $key .= ':';
            $wrappers[$key] = $value;
            $prefix_names[$key] = $prefix_name ? $prefix_name . ' » ' . $info['label'] : $info['label'];
            $nesting_levels[$key] = search_api_list_nesting_level($type);
          }
        }
        unset($wrappers[$prefix]);
      }
    }

    // Add handlers for all indexed fields which weren't processed yet.
    foreach ($fields as $key => $field) {
      if (empty($field['indexed'])) {
        continue;
      }
      $tmp = $orig_wrapper;
      $group = '';
      $name = '';
      $parts = explode(':', $key);
      foreach ($parts as $i => $part) {
        if (!isset($tmp->$part)) {
          continue 2;
        }
        $tmp = $tmp->$part;
        $info = $tmp->info();
        $group = ($group ? $group . ' » ' . $name : ($name ? $name : ''));
        $name = $info['label'];
        if ($i < count($parts) - 1) {
          // Unwrap lists.
          $level = search_api_list_nesting_level($info['type']);
          for ($j = 0; $j < $level; ++$j) {
            $tmp = $tmp[0];
          }
        }
      }
      if ($group) {
        $table[$key]['group'] = $group;
      }
      $table[$key]['title'] = $name;
      $table[$key]['help'] = empty($info['description']) ? NULL : $info['description'];
      $table[$key]['type'] = $field['type'];
      $table[$key] += _search_api_views_add_handlers($field, $tmp);
    }

    // Special handlers
    $table['search_api_id']['title'] = t('Entity ID');
    $table['search_api_id']['help'] = t("The entity's ID.");
    $table['search_api_id']['type'] = 'integer';
    $table['search_api_id']['sort']['handler'] = 'SearchApiViewsHandlerSort';

    $table['search_api_relevance']['group'] = t('Search');
    $table['search_api_relevance']['title'] = t('Relevance');
    $table['search_api_relevance']['help'] = t('The relevance of this search result with respect to the query.');
    $table['search_api_relevance']['type'] = 'decimal';
    $table['search_api_relevance']['field']['handler'] = _search_api_views_field_handler('decimal', 'decimal');
    $table['search_api_relevance']['field']['click sortable'] = TRUE;
    $table['search_api_relevance']['sort']['handler'] = 'SearchApiViewsHandlerSort';

    $table['search_api_views_fulltext']['group'] = t('Search');
    $table['search_api_views_fulltext']['title'] = t('Fulltext search');
    $table['search_api_views_fulltext']['help'] = t('Search several or all fulltext fields at once.');
    $table['search_api_views_fulltext']['type'] = 'text';
    $table['search_api_views_fulltext']['filter']['handler'] = 'SearchApiViewsHandlerFilterFulltext';
    $table['search_api_views_fulltext']['argument']['handler'] = 'SearchApiViewsHandlerArgumentFulltext';
  }
  return $data;
}

/**
 * Helper function for selecting a handler for a field of a specific type.
 */
function _search_api_views_field_handler($type, $inner_type) {
  switch ($inner_type) {
    case 'boolean':
      return 'SearchApiViewsHandlerFieldBoolean';
    case 'date':
      return 'SearchApiViewsHandlerFieldDate';
    case 'duration':
      return 'SearchApiViewsHandlerFieldDuration';
    case 'options':
      return 'SearchApiViewsHandlerFieldOptions';

    default:
      return 'SearchApiViewsHandlerField';
  }
}

/**
 * Helper function that returns an array of handler definitions to add to a
 * views field definition.
 */
function _search_api_views_add_handlers($field, $wrapper) {
  $type = $field['type'];
  $inner_type = search_api_extract_inner_type($type);
  if ($inner_type == 'text') {
    return array(
      'argument' => array(
        'handler' => 'SearchApiViewsHandlerArgumentText',
      ),
      'filter' => array(
        'handler' => 'SearchApiViewsHandlerFilterText',
      ),
    );
  }

  $ret = array();

  if ($wrapper->optionsList()) {
    $ret['filter']['handler'] = 'SearchApiViewsHandlerFilterOptions';
    $ret['filter']['options'] = $wrapper->optionsList();
  }
  elseif ($inner_type == 'boolean') {
    $ret['filter']['handler'] = 'SearchApiViewsHandlerFilterBoolean';
  }
  elseif ($inner_type == 'date') {
    $ret['filter']['handler'] = 'SearchApiViewsHandlerFilterDate';
  }
  else {
    $ret['filter']['handler'] = 'SearchApiViewsHandlerFilter';
  }

  $ret['argument']['handler'] = 'SearchApiViewsHandlerArgument';

  // We can only sort according to single-valued fields.
  if ($type == $inner_type) {
    $ret['sort']['handler'] = 'SearchApiViewsHandlerSort';
  }

  return $ret;
}

/**
 * Implements hook_views_plugins().
 */
function search_api_views_views_plugins() {
  return array(
    'query' => array(
      'search_api_views_query' => array(
        'title' => t('Search API Query'),
        'help' => t('Query will be generated and run using the Search API.'),
        'handler' => 'SearchApiViewsQuery'
      ),
    ),
  );
}
