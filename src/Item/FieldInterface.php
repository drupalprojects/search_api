<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\FieldInterface.
 */

namespace Drupal\search_api\Item;

/**
 * Represents a field on a search api item.
 */
interface FieldInterface {

  /**
   * Create instance.
   *
   * @param string $property_path
   *   The property path of this field.
   * @param string $type
   *   The simple data type of this field.
   * @param mixed $value
   *   The value of this field.
   * @param string $original_type
   *   The original type of this field.
   */
  public function __construct($property_path, $type, $value, $original_type = NULL);

  /**
   * Return the data type of the field.
   *
   * @return string
   *   The data type of the field.
   */
  public function getType();

  /**
   * Set the data type of the field.
   *
   * @param string $type
   *   The data type of the field.
   */
  public function setType($type);

  /**
   * Return the value of the field.
   *
   * @return mixed
   *   The value of the field.
   */
  public function getValue();

  /**
   * Set the value of the field.
   *
   * @param mixed $value
   *   The value of the field.
   */
  public function setValue($value);

  /**
   * Return the original data type.
   *
   * @return string
   *   The original data type.
   */
  public function getOriginalType();

  /**
   * Set the original data type.
   *
   * @param string $original_type
   *   The original data type.
   */
  public function setOriginalType($original_type);

  /**
   * Return the property path.
   *
   * @return string
   *   The property path.
   */
  public function getPropertyPath();

  /**
   * Set the property path.
   *
   * @param string $property_path
   *   The property path.
   */
  public function setPropertyPath($property_path);
}
