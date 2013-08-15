<?php

/**
 * @file
 * Contains \Drupal\search_api\Annotation\SearchApiProcessor.
 */

namespace Drupal\search_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a SearchApiProcessor annotation object.
 *
 * @Annotation
 */
class SearchApiProcessor extends Plugin {

  /**
   * The processor plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the processor plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $name;

  /**
   * The description of the processor.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * The weight of the processor.
   *
   * @ingroup plugin_translatable
   *
   * @var int|NULL
   */
  public $weight;

}
