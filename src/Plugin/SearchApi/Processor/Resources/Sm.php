<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sm.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Sm implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public function getRegularExpression() {
    return '\x{21}\x{23}\x{25}';
  }
}