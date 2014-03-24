<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServicePluginManager.
 */

namespace Drupal\search_api\Service;

use Traversable;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;

/**
 * Search API service plugin manager.
 */
class ServicePluginManager extends DefaultPluginManager {

  /**
   * Create a ServicePluginManager object.
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
    parent::__construct('Plugin/SearchApi/Service', $namespaces, $module_handler, 'Drupal\search_api\Annotation\SearchApiService');
    // Configure the plugin manager.
    $this->setCacheBackend($cache_backend, $language_manager, 'search_api_services');
    $this->alterInfo('search_api_service_info');
  }

}
