<?php

/**
 * @file
 * Views hook implementations for the Search API module.
 */

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility;

/**
 * Implements hook_views_data().
 */
function search_api_views_data() {
  $data = array();
  try {
    /** @var \Drupal\search_api\IndexInterface $index */
    foreach (Index::loadMultiple() as $index) {
      // Fill in base data.
      $key = 'search_api_index_' . $index->id();
      $table = &$data[$key];
      $table['table']['group'] = t('Index @name', array('@name' => $index->label()));
      $table['table']['base'] = array(
        'field' => 'search_api_id',
        'index' => $index->id(),
        'title' => t('Index @name', array('@name' => $index->label())),
        'help' => t('Use the %name search index for filtering and retrieving data.', array('%name' => $index->label())),
        'query_id' => 'search_api_query',
      );

      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($index->getFields() as $field_id => $field) {
        $field_alias = _search_api_views_find_field_alias($field_id, $table);
        $field_definition = _search_api_views_get_handlers($field);
        if ($field_definition) {
          $field_definition += array(
            'title' => $field->getLabel(),
            'help' => $field->getDescription(),
          );
          if ($datasource = $field->getDatasource()) {
            $field_definition['group'] = $datasource->label();
          }
          if ($definition = $field->getDataDefinition()) {
            $field_definition['title short'] = $definition->getLabel();
          }
          if ($field_id != $field_alias) {
            $field_definition['real field'] = $field_id;
          }
          $table[$field_alias] = $field_definition;
        }
      }

      if (isset($table['search_api_language']['filter']['id'])) {
        $table['search_api_language']['filter']['id'] = 'search_api_language';
        $table['search_api_language']['filter']['allow empty'] = FALSE;
      }

      // Add handlers for special fields.
      $table['search_api_id']['title'] = t('Entity ID');
      $table['search_api_id']['help'] = t("The entity's ID");
      $table['search_api_id']['field']['id'] = 'numeric';
      $table['search_api_id']['sort']['id'] = 'search_api';

      $table['search_api_datasource']['title'] = t('Datasource');
      $table['search_api_datasource']['help'] = t("The data source ID");
      $table['search_api_datasource']['field']['id'] = 'standard';
      $table['search_api_datasource']['filter']['id'] = 'search_api_datasource';
      $table['search_api_datasource']['sort']['id'] = 'search_api';

      $table['search_api_relevance']['group'] = t('Search');
      $table['search_api_relevance']['title'] = t('Relevance');
      $table['search_api_relevance']['help'] = t('The relevance of this search result with respect to the query');
      $table['search_api_relevance']['field']['type'] = 'decimal';
      $table['search_api_relevance']['field']['id'] = 'numeric';
      $table['search_api_relevance']['field']['click sortable'] = TRUE;
      $table['search_api_relevance']['sort']['id'] = 'search_api';

      $table['search_api_excerpt']['group'] = t('Search');
      $table['search_api_excerpt']['title'] = t('Excerpt');
      $table['search_api_excerpt']['help'] = t('The search result excerpted to show found search terms');
      $table['search_api_excerpt']['field']['id'] = 'search_api_excerpt';

      $table['search_api_fulltext']['group'] = t('Search');
      $table['search_api_fulltext']['title'] = t('Fulltext search');
      $table['search_api_fulltext']['help'] = t('Search several or all fulltext fields at once.');
      $table['search_api_fulltext']['filter']['id'] = 'search_api_fulltext';
      $table['search_api_fulltext']['argument']['id'] = 'search_api_fulltext';

      $table['search_api_more_like_this']['group'] = t('Search');
      $table['search_api_more_like_this']['title'] = t('More like this');
      $table['search_api_more_like_this']['help'] = t('Find similar content.');
      $table['search_api_more_like_this']['argument']['id'] = 'search_api_more_like_this';

      // @todo Add an "All taxonomy terms" contextual filter (if applicable).
    }
  }
  catch (Exception $e) {
    watchdog_exception('search_api', $e);
  }
  return $data;
}

/**
 * Implements hook_views_plugins_cache_alter().
 */
function search_api_views_plugins_cache_alter(array &$plugins) {
  // Collect all base tables provided by this module.
  $bases = array();
  /** @var \Drupal\search_api\IndexInterface $index */
  foreach (Index::loadMultiple() as $index) {
    $bases[] = 'search_api_index_' . $index->id();
  }
  $plugins['search_api']['base'] = $bases;
}

/**
 * Implements hook_views_plugins_row_alter().
 */
function search_api_views_plugins_row_alter(array &$plugins) {
  // Collect all base tables provided by this module.
  $bases = array();
  /** @var \Drupal\search_api\IndexInterface $index */
  foreach (Index::loadMultiple() as $index) {
    $bases[] = 'search_api_index_' . $index->id();
  }
  $plugins['search_api']['base'] = $bases;
}

/**
 * Finds an unused field alias for a field in a Views table definition.
 *
 * @param string $field_id
 *   The original ID of the Search API field.
 * @param array $table
 *   The Views table definition.
 *
 * @return string
 *   The field alias to use.
 */
function _search_api_views_find_field_alias($field_id, array &$table) {
  $base = $field_alias = preg_replace('/[^a-zA-Z0-9]+/S', '_', $field_id);
  $i = 0;
  while (isset($table[$field_alias])) {
    $field_alias = $base . '_' . ++$i;
  }
  return $field_alias;
}

/**
 * Returns the Views handlers to use for a given field.
 *
 * @param \Drupal\search_api\Item\FieldInterface $field
 *   The field to add to the definition.
 *
 * @return array
 *   The Views definition to add for the given field.
 */
function _search_api_views_get_handlers(FieldInterface $field) {
  $mapping = _search_api_views_handler_mapping();

  try {
    $types = array();
    $definition = $field->getDataDefinition();

    $definition = Utility::getInnerProperty($definition);

    if ($definition instanceof FieldDefinitionInterface) {
      if ($definition->getType() == 'entity_reference') {
        $types[] = 'entity:' . $definition->getSetting('target_type');
        $types[] = 'entity';
      }
    }
    // @todo Detect fields with fixed lists of options, add type "options".
    $types[] = $field->getType();
    /** @var \Drupal\search_api\DataType\DataTypeInterface $data_type */
    $data_type = \Drupal::service('plugin.manager.search_api.data_type')->createInstance($field->getType());
    if (!$data_type->isDefault()) {
      $types[] = $data_type->getFallbackType();
    }

    foreach ($types as $type) {
      if (isset($mapping[$type])) {
        _search_api_views_handler_adjustments($type, $field, $mapping[$type]);
        return $mapping[$type];
      }
    }
  }
  catch (SearchApiException $e) {
    $vars['%index'] = $field->getIndex()->label();
    $vars['%field'] = $field->getPrefixedLabel();
    watchdog_exception('search_api', $e, '%type while Views handlers for field %field on index %index: @message in %function (line %line of %file).', $vars);
  }

  return array();
}

/**
 * Makes necessary, field-specific adjustments to Views handler definitions.
 *
 * @param string $type
 *   The type of field, as defined in _search_api_views_handler_mapping().
 * @param \Drupal\search_api\Item\FieldInterface $field
 *   The field whose handler definitions are being created.
 * @param array $definitions
 *   The handler definitions for the field, as a reference.
 */
function _search_api_views_handler_adjustments($type, FieldInterface $field, array &$definitions) {
  $data_definition = $field->getDataDefinition();
  if ($type == 'entity:taxonomy_term') {
    if (isset($data_definition->getSettings()['handler_settings']['target_bundles'])) {
      $target_bundles = $data_definition->getSettings()['handler_settings']['target_bundles'];
      if (count($target_bundles) == 1) {
        $definitions['filter']['vocabulary'] = reset($target_bundles);
      }
    }
  }
  // By default, all fields can be empty (or at least have to be treated that
  // way by the Search API).
  if (!isset($definitions['filter']['allow empty'])) {
    $definitions['filter']['allow empty'] = TRUE;
  }
}

/**
 * Determines the mapping of Search API data types to their Views handlers.
 *
 * @return array
 *   An associative array with data types as the keys and Views field data
 *   definitions as the values. In addition to all normally defined data types,
 *   keys can also be "options" for any field with an options list, "entity" for
 *   general entity-typed fields or "entity:ENTITY_TYPE" (with "ENTITY_TYPE"
 *   being the machine name of an entity type) for entities of that type.
 *
 * @see search_api_views_handler_mapping_alter()
 */
function _search_api_views_handler_mapping() {
  $mapping = &drupal_static(__FUNCTION__);

  if (!isset($mapping)) {
    $mapping = array(
      'boolean' => array(
        'filter' => array(
          'id' => 'search_api_boolean',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'date' => array(
        'filter' => array(
          'id' => 'search_api_date',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'decimal' => array(
        'filter' => array(
          'id' => 'search_api_numeric',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'integer' => array(
        'filter' => array(
          'id' => 'search_api_numeric',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'string' => array(
        'filter' => array(
          'id' => 'search_api_string',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'text' => array(
        'filter' => array(
          'id' => 'search_api_text',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'options' => array(
        'filter' => array(
          'id' => 'search_api_options',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'entity:taxonomy_term' => array(
        'filter' => array(
          'id' => 'search_api_term',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
      'entity:user' => array(
        'filter' => array(
          'id' => 'search_api_user',
        ),
        'sort' => array(
          'id' => 'search_api',
        ),
      ),
    );

    $alter_id = 'search_api_views_handler_mapping';
    \Drupal::moduleHandler()->alter($alter_id, $mapping);
  }

  return $mapping;
}
