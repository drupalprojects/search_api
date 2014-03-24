<?php
/**
 * @file
 * Contains \Drupal\search_api\Annotation\Datasource.
 */

namespace Drupal\search_api\Annotation;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Search API datasource annotation object.
 *
 * @Annotation
 */
class Datasource extends Plugin {

  /**
   * The datasource plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the datasource plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The description of the datasource.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
