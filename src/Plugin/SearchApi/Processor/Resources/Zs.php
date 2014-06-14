<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zs.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Zs implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}