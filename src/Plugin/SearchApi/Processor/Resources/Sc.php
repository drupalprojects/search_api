<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sc.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Sc implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}