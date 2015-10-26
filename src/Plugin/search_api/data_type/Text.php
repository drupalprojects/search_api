<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\data_type\Text.
 */

namespace Drupal\search_api\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a full text data type.
 *
 * @SearchApiDataType(
 *   id = "text",
 *   label = @Translation("Fulltext"),
 *   description = @Translation("A fulltext field"),
 *   default = "true"
 * )
 */
class Text extends DataTypePluginBase {

}
