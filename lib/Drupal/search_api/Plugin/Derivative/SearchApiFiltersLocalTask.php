<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Derivative\SearchApiFiltersLocalTask.
 */

namespace Drupal\search_api\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DerivativeBase;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeInterface;
use Drupal\search_api\IndexStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for index filters.
 */
class SearchApiFiltersLocalTask extends DerivativeBase implements ContainerDerivativeInterface {

  /**
   * The index storage.
   *
   * @var \Drupal\search_api\IndexStorageInterface
   */
  protected $indexStorage;

  /**
   * Constructs a new SearchApiFiltersLocalTask object.
   *
   * @param \Drupal\search_api\IndexStorageInterface $index_storage
   *   The index storage.
   */
  public function __construct(IndexStorageInterface $index_storage) {
    $this->indexStorage = $index_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity.manager')->getStorage('search_api_index')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = array();

    foreach ($this->indexStorage->loadMultiple() as $index) {
      /** @var $index \Drupal\search_api\Index\IndexInterface */
      $this->derivatives[$index->id()] = array(
        'title' => 'Filters',
        'route_name' => 'search_api.index_filters',
        'base_route' => 'search_api.index_view',
        'weight' => 20,
      );

      // Add secondary tabs for each datasource from this index.
      $weight = 0;
      foreach ($index->getDatasources() as $datasource) {
        $this->derivatives[$index->id() . '_' . $datasource->getPluginId()] = array(
          'title' => $datasource->getPluginDefinition()['label'],
          'route_name' => 'search_api.index_filters_datasource',
          'route_parameters' => array(
            'datasource_id' => $datasource->getPluginId(),
          ),
          'base_route' => 'search_api.index_view',
          'parent_id' => 'search_api.index_filters:' . $index->id(),
          'weight' => $weight++,
        );
      }
    }

    return $this->derivatives;
  }

}
