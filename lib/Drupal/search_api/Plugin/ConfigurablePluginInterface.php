<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\ConfigurablePluginInterface.
 */

namespace Drupal\search_api\Plugin;

use Drupal\Component\Plugin\ConfigurablePluginInterface as DrupalConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface which describes a plugin for Search API.
 */
interface ConfigurablePluginInterface extends PluginInspectionInterface, DrupalConfigurablePluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Get the label for use on the administration pages.
   *
   * @return string
   *   The administration label.
   */
  public function label();

  /**
   * Get the summary of the plugin configuration.
   *
   * @return string
   *   The configuration summary.
   */
  public function summary();

}
