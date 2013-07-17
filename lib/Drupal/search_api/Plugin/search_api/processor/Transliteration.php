<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\processor\Transliteration.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

/**
 * Processor for making searches insensitive to accents and other non-ASCII characters.
 */
class Transliteration extends ProcessorPluginBase {

  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $value = transliteration_get($value, '', language_default('language'));
    }
  }

}
