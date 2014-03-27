<?php

/**
 * @file
 * Deines the class Drupal\search_api\Utility\Utility.
 */

namespace Drupal\search_api\Utility;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Server\ServerInterface;

/**
 * Utility methods.
 *
 * Presently just a wrapper around the previous procedural functions.
 * @todo Needs breaking up. Field related methods seperate?
 */
class Utility {

  /**
   * Determines whether a field of the given type contains text data.
   *
   * @param string $type
   *   A string containing the type to check.
   * @param array $text_types
   *   Optionally, an array of types to be considered as text.
   *
   * @return bool
   *   TRUE if $type is either one of the specified types, or a list of such
   *   values. FALSE otherwise.
   */
  static function isTextType($type, array $text_types = array('text')) {
    return in_array($type, $text_types);
  }

  /**
   * Checks whether it is possible to sort on fields of the given type.
   *
   * @param $type
   *   The type to check for.
   *
   * @todo
   *   Make sure you take the field object and check the isMultiple parameter
   *
   * @return bool
   *   TRUE if this type is sortable, FALSE otherwise.
   */
  static function isSortableType($type) {
    return !static::isTextType($type);
  }

  /**
   * Returns all field types recognized by the Search API framework.
   *
   * @return array
   *   An associative array with all recognized types as keys, mapped to their
   *   translated display names.
   *
   * @see getDefaultDataTypes()
   * @see getDataTypeInfo()
   */
  static function getDataTypes() {
    $types = self::getDefaultDataTypes();
    foreach (self::getDataTypeInfo() as $id => $type) {
      $types[$id] = $type['name'];
    }

    return $types;
  }

  /**
   * Get the mapping between data types and field types
   *
   * @return array
   *   $mapping array with the field type that is requested and it's default data type for a sensible default
   */
  static function getFieldTypeMapping() {
    // @todo Add (static) caching.
    /** @var \Drupal\Core\Field\FieldTypePluginManager $field_type_service */
    $field_type_service = \Drupal::service('plugin.manager.field.field_type');
    $field_types = $field_type_service->getDefinitions();

    $mapping = array();
    foreach ($field_types as $field_type_id => $field_type) {
      switch ($field_type_id) {
        case 'comment':
        case 'list_text':
        case 'text':
        case 'text_long':
        case 'text_with_summary':
          $mapping[$field_type_id] = 'text';
          break;
        case 'path':
        case 'uri':
        case 'email':
        case 'language':
        case 'string':
        case 'string_long':
        case 'token':
        case 'uuid':
          $mapping[$field_type_id] = 'string';
          break;
        case 'datetime':
        case 'date':
        case 'changed':
        case 'created':
        case 'timestamp':
          $mapping[$field_type_id] = 'date';
          break;
        case 'list_boolean':
        case 'boolean':
          $mapping[$field_type_id] = 'boolean';
          break;
        case 'list_float':
        case 'float':
          $mapping[$field_type_id] = 'float';
          break;
        case 'list_integer':
        case 'integer':
          $mapping[$field_type_id] = 'integer';
          break;
        case 'decimal':
          $mapping[$field_type_id] = 'decimal';
          break;
          // We can't include a default here. Only types that are explicitly
          // supported should show up here.
      }
    }
    // Allow other modules to intercept and define what default type they want to use for their field type.
    \Drupal::moduleHandler()->alter('search_api_field_type_mapping', $mapping);
    return $mapping;
  }

  /**
   * Returns the default field types recognized by the Search API framework.
   *
   * @return array
   *   An associative array with the default types as keys, mapped to their
   *   translated display names.
   */
  static function getDefaultDataTypes() {
    return array(
      'text' => t('Fulltext'),
      'string' => t('String'),
      'integer' => t('Integer'),
      'decimal' => t('Decimal'),
      'date' => t('Date'),
      'boolean' => t('Boolean'),
    );
  }

  /**
   * Returns either all custom field type definitions, or a specific one.
   *
   * @param $type
   *   If specified, the type whose definition should be returned.
   *
   * @return array
   *   If $type was not given, an array containing all custom data types, in the
   *   format specified by hook_search_api_data_type_info().
   *   Otherwise, the definition for the given type, or NULL if it is unknown.
   *
   * @see hook_search_api_data_type_info()
   */
  static function getDataTypeInfo($type = NULL) {
    $types = &drupal_static(__FUNCTION__);
    if (!isset($types)) {
      $default_types = search_api_default_data_types();
      $types =  \Drupal::moduleHandler()->invokeAll('search_api_data_type_info');
      $types = $types ? $types : array();
      foreach ($types as &$type_info) {
        if (!isset($type_info['fallback']) || !isset($default_types[$type_info['fallback']])) {
          $type_info['fallback'] = 'string';
        }
      }
      \Drupal::moduleHandler()->alter('search_api_data_type_info', $types);
    }
    if (isset($type)) {
      return isset($types[$type]) ? $types[$type] : NULL;
    }
    return $types;
  }

  /**
   * Extracts specific field values from a complex data object.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The item from which fields should be extracted.
   * @param array $fields
   *   The fields to extract, passed by reference. The format is the same as the
   *   "fields" sub-array in the index options, i.e., an array with the field
   *   names as keys and arrays of field information as values, at least
   *   containing a "type" key. "value" and "original_type" keys will be added for
   *   all fields.
   */
  static function extractFields(ComplexDataInterface $item, array &$fields) {
    // Figure out which fields are directly on the item and which need to be
    // extracted from nested items.
    $direct_fields = array();
    $nested_fields = array();
    foreach (array_keys($fields) as $key) {
      if (strpos($key, ':') !== FALSE) {
        list($direct, $nested) = explode(':', $key, 2);
        $nested_fields[$direct][$nested] = &$fields[$key];
      }
      else {
        $direct_fields[] = $key;
      }
    }
    // Extract the direct fields.
    foreach ($direct_fields as $key) {
      // Set defaults if something fails or the field is empty.
      $fields[$key]['value'] = array();
      $fields[$key]['original_type'] = NULL;
      try {
        $item = $item->get($key);
        self::extractField($item, $fields[$key]);
      }
      catch (\InvalidArgumentException $e) {
        // No need to do anything, we already set the defaults.
      }
    }
    // Recurse for all nested fields.
    foreach ($nested_fields as $direct => $fields_nested) {
      $success = FALSE;
      try {
        $item_nested = $item->get($direct);
        if ($item_nested instanceof ComplexDataInterface && !$item_nested->isEmpty()) {
          self::extractFields($item_nested, $fields_nested);
          $success = TRUE;
        }
      }
      catch (\InvalidArgumentException $e) {
        // Will be automatically handled because $success == FALSE.
      }
      // If the values couldn't be extracted from the nested item, we have to
      // set the defaults here.
      if (!$success) {
        foreach (array_keys($fields_nested) as $key) {
          $fields[$key]['value'] = array();
          $fields[$key]['original_type'] = $fields[$key]['type'];
        }
      }
    }
  }

  /**
   * Extracts value and original type from a single piece of data.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The piece of data from which to extract information.
   * @param array $field
   *   The field information array into which to put the extracted information.
   */
  static function extractField(TypedDataInterface $data, array $field) {
    if ($data->getDataDefinition()->isList()) {
      foreach ($data as $piece) {
        self::extractField($piece, $field);
      }
      return;
    }
    $value = $data->getValue();
    $definition = $data->getDataDefinition();
    if ($definition instanceof ComplexDataDefinitionInterface) {
      $property = $definition->getMainPropertyName();
      if (isset($value[$property])) {
        $field['value'][] = $value[$property];
      }
    }
    else {
      $field['value'][] = reset($value);
    }
    // @todo Figure out how to make this less specific. fago mentioned some
    // hierarchy/inheritance for types, with non-complex types inheriting from
    // one of a few primitive types – maybe we can track that back?
    // Also, is the "field_item:" prefix necessary or always there?
    $field['original_type'] = $definition->getDataType();
  }

  /**
   * Removes all pending server tasks from the list.
   *
   * To remove tasks from an individual server see Server::tasksDelete().
   *
   * @param array|null $ids
   *   (optional) The IDs of the pending server tasks to delete. Set to NULL
   *   to not filter by IDs.
   * @param \Drupal\search_api\Index\IndexInterface|string|null $index
   *   (optional) An index (or its machine name) for which the tasks should be
   *   deleted. Set to NULL to delete tasks for all indexes.
   */
  static function serverTasksDelete(array $ids = NULL, $index = NULL) {
    $delete = db_delete('search_api_task');
    if ($ids) {
      $delete->condition('id', $ids);
    }
    if ($index) {
      $delete->condition('index_id', is_object($index) ? $index->id() : $index);
    }
    $delete->execute();
  }

  /**
   * Sorts arrays on weight.
   */
  static function sortByWeight($element_a, $element_b) {
    if (!isset($element_a['weight'])) {
      $element_a['weight'] = 0;
    }
    if (!isset($element_b['weight'])) {
      $element_b['weight'] = 0;
    }
    if ($element_a['weight'] == $element_b['weight']) {
      return 0;
    }
    return ($element_a['weight'] < $element_b['weight']) ? -1 : 1;
  }

  /**
   * Creates a new search query object.
   *
   * @param IndexInterface $index
   *   The index on which to search.
   * @param array $options
   *   (optional) The options to set for the query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query object to use.
   *
   * @see \Drupal\search_api\Query\QueryInterface::create()
   */
  public static function createQuery(IndexInterface $index, array $options = array()) {
    return Query::create($index, $options);
  }

}
