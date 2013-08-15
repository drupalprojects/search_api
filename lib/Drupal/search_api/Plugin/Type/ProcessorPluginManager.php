<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Type\ProcessorPluginManager.
 */

namespace Drupal\search_api\Plugin\Type;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for the SearchApi service plugin.
 */
class ProcessorPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    $this->setCacheBackend($cache_backend, $language_manager, 'search_api_processor_info');

    $annotation_namespaces = array('Drupal\search_api\Annotation' => $namespaces['Drupal\search_api']);
    parent::__construct('Plugin/search_api/processor', $namespaces, $annotation_namespaces, 'Drupal\search_api\Annotation\SearchApiProcessor');
  }

}
