<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Derivative\SearchApiFieldsLocalTask.
 */

namespace Drupal\search_api\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DerivativeBase;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for index fields.
 */
class SearchApiFieldsLocalTask extends DerivativeBase implements ContainerDerivativeInterface {

  /**
   * The index storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $storage;

  /**
   * Constructs a new SearchApiFieldsLocalTask object.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorage $storage
   *   The index storage.
   */
  public function __construct(ConfigEntityStorage $storage) {
    $this->storage = $storage;
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

    foreach ($this->storage->loadMultiple() as $index) {
      /** @var $index \Drupal\search_api\Index\IndexInterface */
      $this->derivatives[$index->id()] = array(
        'title' => 'Fields',
        'route_name' => 'search_api.index_fields',
        'base_route' => 'search_api.index_view',
        'weight' => 10,
      );

      // Add secondary tabs for each datasource from this index.
      $weight = 0;
      foreach ($index->getDatasources() as $datasource) {
        $this->derivatives[$index->id() . '_' . $datasource->getPluginId()] = array(
          'title' => $datasource->getPluginDefinition()['label'],
          'route_name' => 'search_api.index_fields_datasource',
          'route_parameters' => array(
            'datasource_id' => $datasource->getPluginId(),
          ),
          'base_route' => 'search_api.index_view',
          'parent_id' => 'search_api.index_fields:' . $index->id(),
          'weight' => $weight++,
        );
      }
    }

    return $this->derivatives;
  }

}
