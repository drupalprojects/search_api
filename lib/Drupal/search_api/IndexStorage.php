<?php

/**
 * @file
 * Definition of Drupal\search_api\IndexStorage.
 */

namespace Drupal\search_api;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the controller class for indexes.
 *
 * This extends the Drupal\Core\Entity\ConfigStorageController class, adding
 * required special handling for index entities.
 */
class IndexStorage extends ConfigEntityStorage implements IndexStorageInterface {


  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory;
   */
  protected $queryFactory;

  /**
   * Constructs a IndexStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The query factory.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, StorageInterface $config_storage, UuidInterface $uuid_service, EntityManagerInterface $entity_manager, QueryFactory $query_factory) {
    parent::__construct($entity_type, $config_factory, $config_storage, $uuid_service);
    $this->entityManager = $entity_manager;
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('uuid'),
      $container->get('entity.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexesForEntity(ContentEntityInterface $entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $indexes = array();

    $index_names = $this->queryFactory->get('search_api_index', 'AND')
      ->condition('datasourcePluginId', $entity_type_id, 'ENDS_WITH')
      ->execute();

    if (!empty($index_names)) {
      $indexes = $this->loadMultiple($index_names);
    }

    // Check if the index is set to excluded or included.
    // datasourcePluginConfig.config = 1, exclude from indexing if the bundle exists in the datasourcePluginConfig.bundles
    // datasourcePluginConfig.config = 0, exclude from indexing if the bundle does not exists
    foreach ($indexes as $index_id => $index) {
      if (isset($index->datasourcePluginConfig['default'])) {
        if (($index->datasourcePluginConfig['default'] == 1) && !empty($index->datasourcePluginConfig['bundles'][$entity_bundle])) {
          unset($indexes[$index_id]);
        }
        if (($index->datasourcePluginConfig['default'] == 0) && empty($index->datasourcePluginConfig['bundles'][$entity_bundle])) {
          unset($indexes[$index_id]);
        }
      }
    }

    return $indexes;
  }
}
