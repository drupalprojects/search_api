<?php
/**
 * @file
 * Contains \Drupal\search_api\Annotation\Service.
 */

namespace Drupal\search_api\Annotation;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Search API service annotation object.
 *
 * @Annotation
 */
class Service extends Plugin {

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
  public $label;

  /**
   * The service description.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
