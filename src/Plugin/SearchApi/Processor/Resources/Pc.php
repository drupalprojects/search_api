<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pc.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

class Pc implements unicodeListInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{005F}\x{203F}\x{2040}\x{2054}\x{FE33}\x{FE34}\x{FE4D}' .
      '\x{FE4E}\x{FE4F}\x{FF3F}';
  }
}
