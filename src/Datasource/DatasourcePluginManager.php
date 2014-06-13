<?php

/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginManager.
 */

namespace Drupal\search_api\Datasource;

use Drupal\Component\Utility\String;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Search API datasource plugin manager.
 */
class DatasourcePluginManager extends DefaultPluginManager {

  /**
   * Create a DatasourcePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    // Initialize the parent chain of objects.
    parent::__construct('Plugin/SearchApi/Datasource', $namespaces, $module_handler, 'Drupal\search_api\Annotation\SearchApiDatasource');
    // Configure the plugin manager.
    $this->setCacheBackend($cache_backend, 'search_api_datasources');
    $this->alterInfo('search_api_datasource_info');
  }

  /**
   * Get a list of plugin definition labels.
   *
   * @return array
   *   An associative array containing the plugin label, keyed by the plugin ID.
   */
  public function getDefinitionLabels() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the datasource plugin definitions.
    foreach ($this->getDefinitions() as $plugin_id => $plugin_definition) {
      /** @var \Drupal\Core\Entity\FieldableEntityStorageInterface $storage */
      $storage = \Drupal::entityManager()->getStorage($plugin_definition['entity_type']);

      if (!$storage instanceof \Drupal\Core\Entity\ContentEntityNullStorage) {
        // Add the plugin to the list.
        $options[$plugin_id] = String::checkPlain($plugin_definition['label']);
      }
    }
    return $options;
  }

}
