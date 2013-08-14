<?php

/**
 * @file
 * Contains \Drupal\search_api\Annotation\SearchApiService.
 */

namespace Drupal\search_api\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a SearchApiService annotation object.
 *
 * @Annotation
 */
class SearchApiService extends Plugin {

  /**
   * The service plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the service plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $name;

  /**
   * The service description.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
