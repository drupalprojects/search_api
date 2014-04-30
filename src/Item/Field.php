<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\Field.
 */

namespace Drupal\search_api\Item;

/**
 * Represents a field on a search api item.
 *
 * @TODO Remove ArrayAccess - used for backward compatibility while porting.
 */
class Field implements FieldInterface, \ArrayAccess {

  /**
   * The property path on the item source.
   * @var string
   */
  protected $propertyPath;

  /**
   * The simple data type of this field.
   * @var string
   */
  protected $type;

  /**
   * The field value.
   * @var mixed
   */
  protected $value;

  /**
   * The source data type of this field.
   * @var string
   */
  protected $originalType;

  /**
   * {@inheritdoc}
   */
  public function __construct($property_path, $type, $value, $original_type = NULL) {
    $this->propertyPath = $property_path;
    $this->type = $type;
    $this->value = $value;
    $this->originalType = $original_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value) {
    $this->value = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalType() {
    return $this->originalType;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalType($original_type) {
    $this->originalType = $original_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyPath() {
    return $this->propertyPath;
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyPath($property_path) {
    $this->propertyPath = $property_path;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset) {
    return property_exists($this, $offset);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset) {
    return $this->{$offset};
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value) {
    $this->{$offset} = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset) {
    $this->{$offset} = NULL;
  }
}
