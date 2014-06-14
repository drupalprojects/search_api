<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cs.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Cs implements unicodeList {

  /**
   * {@inheritdoc}
   */
  public function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}