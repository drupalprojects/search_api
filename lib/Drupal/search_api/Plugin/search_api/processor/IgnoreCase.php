<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\processor\IgnoreCase.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

/**
 * Processor for making searches case-insensitive.
 */
class IgnoreCase extends ProcessorPluginBase {

  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $value = drupal_strtolower($value);
    }
  }

}
