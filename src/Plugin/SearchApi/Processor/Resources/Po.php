<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Po.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Po implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}