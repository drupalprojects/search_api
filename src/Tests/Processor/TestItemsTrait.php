<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TestItemsTrait.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Provides common methods for test cases that need to create search items.
 */
trait TestItemsTrait {

  /**
   * Creates an array with a single item which has the given field.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index that should be used for the item.
   * @param string $field_type
   *   The field type to set for the field.
   * @param mixed $field_value
   *   A field value to add to the field.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   (optional) A variable, passed by reference, into which the created field
   *   will be saved.
   * @param string $field_id
   *   (optional) The field ID to set for the field.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing a single item with the specified field.
   */
  public function createSingleFieldItem(IndexInterface $index, $field_type, $field_value, FieldInterface &$field = NULL, $field_id = 'entity:node|field_test') {
    $item_id = 'entity:node|1:en';
    $item = Utility::createItem($index, $item_id);
    $field = Utility::createField($index, 'entity:node|field_test');
    $field->setType($field_type);
    $field->addValue($field_value);
    $item->setField($field_id, $field);

    return array($item_id => $item);
  }

  /**
   * Creates a certain number of test items.
   *
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index that should be used for the items.
   * @param int $count
   *   The number of items to create.
   * @param array[] $fields
   *   The fields to create on the items, with keys being field IDs and values
   *   being arrays with the following information:
   *   - type: The type to set for the field.
   *   - values: (optional) The values to set for the field.
   * @param \Drupal\Core\TypedData\ComplexDataInterface|null $object
   *   The object to set on each item as the "original object".
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing the requested test items.
   */
  public function createItems(IndexInterface $index, $count, array $fields, ComplexDataInterface $object = NULL) {
    $items = array();
    for ($i = 1; $i <= $count; ++$i) {
      $item_id = "entity:node|$i:en";
      $item = Utility::createItem($index, $item_id);
      if (isset($object)) {
        $item->setOriginalObject($object);
      }
      foreach ($fields as $field_id => $field_info) {
        $field = Utility::createField($index, $field_id)
          ->setType($field_info['type']);
        if (isset($field_info['values'])) {
          $field->setValues($field_info['values']);
        }
        $item->setField($field_id, $field);
      }
      $items[$item_id] = $item;
    }
    return $items;
  }

}
