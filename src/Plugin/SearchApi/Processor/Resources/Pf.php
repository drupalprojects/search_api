<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pf.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Pf implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return 
      '\x{00BB}\x{2019}\x{201D}\x{203A}\x{2E03}\x{2E05}\x{2E0A}' .
      '\x{2E0D}\x{2E1D}\x{2E21}';
  }
}
