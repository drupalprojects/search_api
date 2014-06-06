<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TestItemsTrait.
 */

namespace Drupal\search_api\Tests\Processor;

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

}
