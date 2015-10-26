<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\data_type\String.
 */

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 *
 * @SearchApiDataType(
 *   id = "string",
 *   label = @Translation("String"),
 *   description = @Translation("A string field"),
 *   default = "true"
 * )
 */
class String extends DataTypePluginBase {

}
