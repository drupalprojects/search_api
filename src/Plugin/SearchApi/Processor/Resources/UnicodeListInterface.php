<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\UnicodeListInterface.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;


interface UnicodeListInterface {

  /**
   * Returns the regular expression string
   *
   * @return string
   */
  public static function getRegularExpression();

} 