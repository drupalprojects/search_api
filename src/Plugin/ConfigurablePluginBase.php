<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\ConfigurablePluginBase.
 */

namespace Drupal\search_api\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for all configurable plugins.
 */
abstract class ConfigurablePluginBase extends PluginBase implements ConfigurablePluginInterface {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Get the plugin definition.
    $plugin_definition = $this->getPluginDefinition();
    // Get the plugin definition label.
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    // Get the plugin definition.
    $plugin_definition = $this->getPluginDefinition();
    // Get the plugin definition label.
    return $plugin_definition['description'];
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
  public function buildConfigurationForm(array $form, array &$form_state) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) { }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $this->setConfiguration($form_state['values']);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

}
