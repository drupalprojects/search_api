<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zl.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Zl implements UnicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{2028}';
  }
}
