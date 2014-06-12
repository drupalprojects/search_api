<?php

/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorPluginManager.
 */

namespace Drupal\search_api\Processor;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Search API processor plugin manager.
 */
class ProcessorPluginManager extends DefaultPluginManager {

  /**
   * Create a ProcessorPluginManager object.
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
    parent::__construct('Plugin/SearchApi/Processor', $namespaces, $module_handler, 'Drupal\search_api\Annotation\SearchApiProcessor');
    // Configure the plugin manager.
    $this->setCacheBackend($cache_backend, 'search_api_processors');
    $this->alterInfo('search_api_processor_info');
  }

}
