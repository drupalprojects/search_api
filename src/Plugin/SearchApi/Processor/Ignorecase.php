<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\Component\Utility\Unicode;

/**
 * @SearchApiProcessor(
 *   id = "search_api_ignorecase_processor",
 *   label = @Translation("Ignore case processor"),
 *   description = @Translation("Ignore case in strings")
 * )
 */
class Ignorecase extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $value = Unicode::strtolower($value);
    }
  }

}
