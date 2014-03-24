<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\ConfigurablePluginLifecycleInterface.
 */

namespace Drupal\search_api\Plugin;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

// @todo: Needs to be removed because of lack to context when being executed.

/**
 * Interface which describes a plugin which supports a lifecycle.
 */
interface ConfigurablePluginLifecycleInterface extends ConfigurablePluginInterface, PluginInspectionInterface, PluginFormInterface {

  /**
   * Invoked before plugin instance configuration is created.
   */
  public function preInstanceConfigurationCreate();

  /**
   * Invoked after plugin instance configuration is created.
   */
  public function postInstanceConfigurationCreate();

  /**
   * Invoked before plugin instance configuration is updated.
   */
  public function preInstanceConfigurationUpdate();

  /**
   * Invoked after plugin instance configuration is updated.
   */
  public function postInstanceConfigurationUpdate();

  /**
   * Invoked before plugin instance configuration is deleted.
   */
  public function preInstanceConfigurationDelete();

  /**
   * Invoked after plugin instance configuration is deleted.
   */
  public function postInstanceConfigurationDelete();

}