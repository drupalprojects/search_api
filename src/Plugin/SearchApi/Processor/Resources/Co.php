<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Co.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Co implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}