<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginManager.
 */

namespace Drupal\search_api\Datasource;

use Traversable;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;

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
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    // Initialize the parent chain of objects.
    parent::__construct('Plugin/SearchApi/Datasource', $namespaces, $module_handler, 'Drupal\search_api\Annotation\SearchApiDatasource');
    // Configure the plugin manager.
    $this->setCacheBackend($cache_backend, $language_manager, 'search_api_datasources');
    $this->alterInfo($module_handler, 'search_api_datasource_info');
  }

}
