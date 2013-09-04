<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\AddHierarchy.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Provides a processor for indexing all ancestors of fields with hierarchy.
 *
 * @SearchApiProcessor(
 *   id = "search_api_add_hierarchy",
 *   name = @Translation("Index hierarchy"),
 *   description = @Translation("Allows to index hierarchical fields along with all their ancestors."),
 *   weight = -10
 * )
 */
class AddHierarchy extends ProcessorPluginBase {

  /**
   * Cached value for the hierarchical field options.
   *
   * @var array
   *
   * @see getHierarchicalFields()
   */
  protected static $field_options = array();

  /**
   * Checks whether this processor is applicable for a certain index.
   *
   * Enable this data alteration only if any hierarchical fields are available.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check for.
   *
   * @return boolean
   *   TRUE if the callback can run on the given index; FALSE otherwise.
   */
  public static function supportsIndex(IndexInterface $index) {
    return (bool) self::getHierarchicalFields($index);
  }

  /**
   * Display a form for configuring this callback.
   *
   * @return array
   *   A form array for configuring this callback, or FALSE if no configuration
   *   is possible.
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $options = self::getHierarchicalFields($this->index);
    $this->options += array('fields' => array());
    $form['fields'] = array(
      '#title' => t('Hierarchical fields'),
      '#description' => t('Select the fields which should be supplemented with their ancestors. ' .
          'Each field is listed along with its children of the same type. ' .
          'When selecting several child properties of a field, all those properties will be recursively added to that field. ' .
          'Please note that you should de-select all fields before disabling this data alteration.'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#size' => min(6, count($options, COUNT_RECURSIVE)),
      '#options' => $options,
      '#default_value' => $this->options['fields'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    // Change the saved type of fields in the index, if necessary.
    $fields = $this->index->getOption('fields', array());
    if ($fields) {
      $previous = drupal_map_assoc($this->options['fields']);
      $values = $form_state['values'];
      foreach ($values['fields'] as $field) {
        list($key) = explode(':', $field, 2);
        if (empty($previous[$field]) && isset($fields[$key]['type'])) {
          $fields[$key]['type'] = 'list<' . search_api_extract_inner_type($fields[$key]['type']) . '>';
          $change = TRUE;
        }
      }
      $new = drupal_map_assoc($values['fields']);
      foreach ($previous as $field) {
        list($key) = explode(':', $field, 2);
        if (empty($new[$field]) && isset($fields[$key]['type'])) {
          $w = $this->index->entityWrapper(NULL, FALSE);
          if (isset($w->$key)) {
            $type = $w->$key->type();
            $inner = search_api_extract_inner_type($fields[$key]['type']);
            $fields[$key]['type'] = search_api_nest_type($inner, $type);
            $change = TRUE;
          }
        }
      }
      if (isset($change)) {
        $this->index->options['fields'] = $fields;
        $this->index->save();
      }
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    if (empty($this->options['fields'])) {
      return array();
    }

    $ret = array();
    $wrapper = $this->index->entityWrapper(NULL, FALSE);
    foreach ($this->options['fields'] as $field) {
      list($key, $prop) = explode(':', $field);
      if (!isset($wrapper->$key)) {
        continue;
      }
      $child = $wrapper->$key;
      while (search_api_is_list_type($child->type())) {
        $child = $child[0];
      }
      if (!isset($child->$prop)) {
        continue;
      }
      if (!isset($ret[$key])) {
        $ret[$key] = $child->info();
        $type = search_api_extract_inner_type($ret[$key]['type']);
        $ret[$key]['type'] = "list<$type>";
        $ret[$key]['getter callback'] = 'entity_property_verbatim_get';
        // The return value of info() has some additional internal values set,
        // which we have to unset for the use here.
        unset($ret[$key]['name'], $ret[$key]['parent'], $ret[$key]['langcode'], $ret[$key]['clear'],
            $ret[$key]['property info alter'], $ret[$key]['property defaults']);
      }
      if (isset($ret[$key]['bundle'])) {
        $info = $child->$prop->info();
        if (empty($info['bundle']) || $ret[$key]['bundle'] != $info['bundle']) {
          unset($ret[$key]['bundle']);
        }
      }
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    if (empty($this->options['fields'])) {
      return;
    }
    foreach ($items as $item) {
      $wrapper = $this->index->entityWrapper($item, FALSE);

      $values = array();
      foreach ($this->options['fields'] as $field) {
        list($key, $prop) = explode(':', $field);
        if (!isset($wrapper->$key)) {
          continue;
        }
        $child = $wrapper->$key;

        $values += array($key => array());
        $this->extractHierarchy($child, $prop, $values[$key]);
      }
      foreach ($values as $key => $value) {
        $item->$key = $value;
      }
    }
  }

  /**
   * Helper method for finding all hierarchical fields of an index's type.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index whose fields should be checked.
   *
   * @return array
   *   An array containing all hierarchical fields of the index, structured as
   *   an options array grouped by primary field.
   */
  protected static function getHierarchicalFields(IndexInterface $index) {
    if (!isset(self::$field_options[$index->id()])) {
      self::$field_options[$index->id()] = array();
      $wrapper = $index->entityWrapper(NULL, FALSE);
      // Only entities can be indexed in hierarchies, as other properties don't
      // have IDs that we can extract and store.
      $entity_info = entity_get_info();
      foreach ($wrapper as $key1 => $child) {
        while (search_api_is_list_type($child->type())) {
          $child = $child[0];
        }
        $info = $child->info();
        $type = $child->type();
        if (empty($entity_info[$type])) {
          continue;
        }
        foreach ($child as $key2 => $prop) {
          if (search_api_extract_inner_type($prop->type()) == $type) {
            $prop_info = $prop->info();
            self::$field_options[$index->id()][$info['label']]["$key1:$key2"] = $prop_info['label'];
          }
        }
      }
    }
    return self::$field_options[$index->id()];
  }

  /**
   * Extracts a hierarchy from a metadata wrapper by modifying $values.
   */
  public function extractHierarchy(TypedDataInterface $wrapper, $property, array &$values) {
    if (search_api_is_list_type($wrapper->type())) {
      foreach ($wrapper as $w) {
        $this->extractHierarchy($w, $property, $values);
      }
      return;
    }
    try {
      $v = $wrapper->value(array('identifier' => TRUE));
      if ($v && !isset($values[$v])) {
        $values[$v] = $v;
        if (isset($wrapper->$property) && $wrapper->value() && $wrapper->$property->value()) {
          $this->extractHierarchy($wrapper->$property, $property, $values);
        }
      }
    }
    catch (EntityMetadataWrapperException $e) {
      // Some properties like entity_metadata_book_get_properties() throw
      // exceptions, so we catch them here and ignore the property.
    }
  }

}
