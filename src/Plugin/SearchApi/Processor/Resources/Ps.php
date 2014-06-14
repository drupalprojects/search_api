<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Ps.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Ps implements unicodeList {

  /**
   * {@inheritdoc}
   */
  public function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}