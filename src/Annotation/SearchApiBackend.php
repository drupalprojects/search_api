<?php

/**
 * @file
 * Contains \Drupal\search_api\Annotation\Backend.
 */

namespace Drupal\search_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Search API backend annotation object.
 *
 * @Annotation
 */
class SearchApiBackend extends Plugin {

  /**
   * The backend plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the backend plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The backend description.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
