<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\UnicodeCharacterCategoryInterface.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

/**
 * Defines an interface for classes representing a Unicode character category.
 */
interface UnicodeCharacterCategoryInterface {

  /**
   * Returns a regular expression matching this character class.
   *
   * @return string
   *   A PCRE regular expression.
   */
  public static function getRegularExpression();

}
