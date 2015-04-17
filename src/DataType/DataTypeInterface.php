<?php

/**
 * @file
 * Contains \Drupal\search_api\DataType\DataTypeInterface.
 */

namespace Drupal\search_api\DataType;

use Drupal\search_api\Plugin\ConfigurablePluginInterface;

/**
 * Defines an interface for data type plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypePluginBase
 * @see plugin_api
 */
interface DataTypeInterface extends ConfigurablePluginInterface {

  /**
   * Converts a field value to match the data type (if needed).
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return mixed
   *   The converted value.
   */
  public function getValue($value);

  /**
   * Returns the fallback default data type for this data type.
   *
   * @return string
   *   The fallback default data type.
   *
   * @see \Drupal\search_api\Utility::getDefaultDataTypes()
   */
  public function getFallbackType();

}
