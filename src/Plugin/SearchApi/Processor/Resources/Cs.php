<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cs.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Cs implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{D800}\x{DB7F}\x{DB80}\x{DBFF}\x{DC00}\x{DFFF}';
  }
}
