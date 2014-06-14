<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zp.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Zp implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{2029}';
  }
}
