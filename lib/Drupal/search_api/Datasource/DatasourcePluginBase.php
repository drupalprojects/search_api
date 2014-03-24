<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginBase.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Index\IndexInterface;

/**
 * Abstract base class for search datasource plugins.
 */
abstract class DatasourcePluginBase extends ConfigurablePluginBase implements DatasourceInterface {

  /**
   * The index to which the datasource is attached.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  private $index;

  /**
   * Overrides ConfigurablePluginBase::__construct().
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    // Check if index entry is missing or invalid.
    if (!isset($configuration['_index_']) || !($configuration['_index_'] instanceof IndexInterface)) {
      // Raise PluginException: invalid or missing index object.
      throw new PluginException('Invalid or missing index ');
    }
    // Get the index from configuration.
    $this->index = $configuration['_index_'];
    // Remove the index from configuration.
    unset($configuration['_index_']);
    // Initialize the parent chain of objects.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Get the index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   */
  protected function getIndex() {
    return $this->index;
  }

}
