<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\data_type\BooleanDataType.
 */

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a boolean data type.
 *
 * @SearchApiDataType(
 *   id = "boolean",
 *   label = @Translation("Boolean"),
 *   description = @Translation("A boolean field"),
 *   default = "true"
 * )
 */
class BooleanDataType extends DataTypePluginBase {

}
