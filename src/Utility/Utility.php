<?php

/**
 * @file
 * Deines the class Drupal\search_api\Utility\Utility.
 */

namespace Drupal\search_api\Utility;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Item\Field;

/**
 * Utility methods.
 *
 * Presently just a wrapper around the previous procedural functions.
 * @todo Needs breaking up. Field related methods seperate?
 */
class Utility {

  static $fieldTypeMapping = array();

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
   * Returns all field types recognized by the Search API framework.
   *
   * @return array
   *   An associative array with all recognized types as keys, mapped to their
   *   translated display names.
   *
   * @see \Drupal\search_api\Utility::getDefaultDataTypes()
   * @see \Drupal\search_api\Utility::getDataTypeInfo()
   */
  static function getDataTypes() {
    $types = self::getDefaultDataTypes();
    foreach (self::getDataTypeInfo() as $id => $type) {
      $types[$id] = $type['name'];
    }

    return $types;
  }

  /**
   * Retrieves the mapping for known data types to Search API's internal types.
   *
   * @return array
   *   An array mapping all known (and supported) Drupal data types to their
   *   corresponding Search API data types. Empty values mean that fields of
   *   that type should be ignored by the Search API.
   *
   * @see hook_search_api_field_type_mapping_alter()
   */
  static function getFieldTypeMapping() {
    // Check the static cache first.
    if (empty(static::$fieldTypeMapping)) {
      // It's easier to write and understand this array in the form of
      // $search_api_field_type => array($data_types) and flip it below.
      $default_mapping = array(
        'text' => array(
          'field_item:string_long.string',
          'field_item:text_long.string',
          'field_item:text_with_summary.string',
        ),
        'string' => array(
          'string',
          'email',
          'uri',
          'filter_format',
          'duration_iso8601,'
        ),
        'integer' => array(
          'integer',
          'timespan',
        ),
        'decimal' => array(
          'decimal',
          'float',
        ),
        'date' => array(
          'datetime_iso8601',
          'timestamp',
        ),
        'boolean' => array(
          'boolean',
        ),
        // Types we know about but want/have to ignore.
        NULL => array(
          'language',
        ),
      );

      foreach ($default_mapping as $search_api_type => $data_types) {
        foreach ($data_types as $data_type) {
          $mapping[$data_type] = $search_api_type;
        }
      }

      // Allow other modules to intercept and define what default type they want
      // to use for their data type.
      \Drupal::moduleHandler()->alter('search_api_field_type_mapping', $mapping);

      static::$fieldTypeMapping = $mapping;
    }

    return static::$fieldTypeMapping;
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
      $default_types = Utility::getDefaultDataTypes();
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
   *   The fields to extract. The array keys here are property paths
   *   (i.e., the second part of the field identifier, after the
   *   field ID separator). The values are associative arrays of field
   *   information, at least containing a "type" key. "value" and
   *   "original_type" keys will be added for all fields.
   *
   * @return \Drupal\search_api\Item\Field[]
   *   Array of item field objects.
   */
  static function extractFields(ComplexDataInterface $item, array &$fields) {
    $item_fields = array();
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
        self::extractField($item->get($key), $fields[$key]);
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
        if ($item_nested instanceof DataReferenceInterface) {
          $item_nested = $item_nested->getTarget();
        }
        if ($item_nested instanceof ComplexDataInterface && !$item_nested->isEmpty()) {
          self::extractFields($item_nested, $fields_nested);
          $success = TRUE;
        }
        elseif ($item_nested instanceof ListInterface && !$item_nested->isEmpty()) {
          foreach ($item_nested as $list_item) {
            self::extractFields($list_item, $fields_nested);
          }
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
    // @TODO Move that into extractField().
    foreach ($fields as $property_path => $field) {
      $item_fields[$property_path] = new Field($property_path, $field['type'], $field['value'], $field['original_type']);
    }
    return $item_fields;
  }

  /**
   * Extracts value and original type from a single piece of data.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The piece of data from which to extract information.
   * @param array $field
   *   The field information array into which to put the extracted information.
   */
  static function extractField(TypedDataInterface $data, array &$field) {
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
   */
  static function serverTasksDeleteAll() {
    db_delete('search_api_task')->execute();
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

  /**
   * Returns a deep copy of the input array.
   *
   * The behavior of PHP regarding arrays with references pointing to it is
   * rather weird.
   *
   * @param array $array
   *   The array to copy.
   *
   * @return array
   *   A deep copy of the array.
   */
  public static function deepCopy(array $array) {
    $copy = array();
    foreach ($array as $k => $v) {
      if (is_array($v)) {
        $copy[$k] = static::deepCopy($v);
      }
      elseif (is_object($v)) {
        $copy[$k] = clone $v;
      }
      elseif ($v) {
        $copy[$k] = $v;
      }
    }
    return $copy;
  }

  /**
   * Splits an internal ID into its two parts.
   *
   * Both internal item IDs and internal field identifiers are prefixed with the
   * corresponding datasource ID. This method will split these IDs up again into
   * their two parts.
   *
   * @param string $combined_id
   *   The internal ID, with an optional datasource prefix separated with
   *   \Drupal\search_api\Index\IndexInterface::DATASOURCE_ID_SEPARATOR from the
   *   raw item ID or property path.
   *
   * @return array
   *   A numeric array, containing the datasource ID in element 0 and the raw
   *   item ID or property path in element 1. In the case of
   *   datasource-independent fields (i.e., when there is no prefix), element 0
   *   will be NULL.
   */
  public static function splitCombinedId($combined_id) {
    $pos = strpos($combined_id, IndexInterface::DATASOURCE_ID_SEPARATOR);
    if ($pos === FALSE) {
      return array(NULL, $combined_id);
    }
    return array(substr($combined_id, 0, $pos), substr($combined_id, $pos + 1));
  }

}
