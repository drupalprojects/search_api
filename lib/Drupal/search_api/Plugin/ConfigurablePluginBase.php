<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\ConfigurablePluginBase.
 */

namespace Drupal\search_api\Plugin;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Base class for all configurable plugins.
 */
abstract class ConfigurablePluginBase extends PluginBase implements ConfigurablePluginInterface, PluginFormInterface {

  /**
   * Overrides PluginBase::__construct().
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    // Apply default configuration.
    $configuration += $this->defaultConfiguration();
    // Initialize the parent chain of objects.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) { }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) { }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) { }

}
