<?php

/**
 * @file
 * Contains \Drupal\search_api\DataType\DataTypePluginBase.
 */

namespace Drupal\search_api\DataType;

use Drupal\search_api\Plugin\IndexPluginBase;

/**
 * Defines a base class from which other data type classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_tracker_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the data type class.
 * - label: The human-readable name of the data type class, translated.
 * - description: A human-readable description for the data type class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiDataType(
 *   id = "my_data_type",
 *   label = @Translation("My data type"),
 *   description = @Translation("Some information about my data type.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see plugin_api
 */
abstract class DataTypePluginBase extends IndexPluginBase implements DataTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackType() {
    return 'text';
  }

}
