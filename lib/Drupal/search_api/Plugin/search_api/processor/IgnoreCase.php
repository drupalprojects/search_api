<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\IgnoreCase.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Makes searches case-insensitive by transforming all strings to lowercase.
 *
 * @SearchApiProcessor(
 *   id = "search_api_case_ignore",
 *   name = @Translation("Ignore case"),
 *   description = @Translation("This processor will make searches case-insensitive for fulltext or string fields.")
 * )
 */
class IgnoreCase extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $value = drupal_strtolower($value);
    }
  }

}
