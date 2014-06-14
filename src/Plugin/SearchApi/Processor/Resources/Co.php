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
    return
      '\x{E000}\x{F8FF}\x{F0000}\x{FFFFD}\x{100000}\x{10FFFD}';
  }
}
