<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\data_type\DateDataType.
 */

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a date data type.
 *
 * @SearchApiDataType(
 *   id = "date",
 *   label = @Translation("Date"),
 *   description = @Translation("A date field"),
 *   default = "true"
 * )
 */
class DateDataType extends DataTypePluginBase {

}
