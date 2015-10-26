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

  /**
   * Determines whether this data type is a default data type.
   *
   * Default data types are provided by the Search API module itself and have to
   * be supported by all backends. They therefore are the only ones that can be
   * used as a fallback for other data types, and don't need to have a fallback
   * type themselves.
   *
   * @return bool
   *   TRUE if the data type is a default type, FALSE otherwise.
   */
  public function isDefault();

}
