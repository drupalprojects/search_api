<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

/*
 * Include required classes and interfaces.
 */
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Annotation\Datasource;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Datasource\Entity\EntityDatasourceItem;
use Drupal\search_api\Index\IndexInterface;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @Datasource(
 *   id = "search_api_content_entity_datasource",
 *   label = @Translation("Content entity datasource"),
 *   desciption = @Translation("Exposes the content entities as datasource."),
 *   derivative = "Drupal\search_api\Datasource\Entity\ContentEntityDatasourceDerivative"
 * )
 */
class ContentEntityDatasource extends DatasourcePluginBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

  /**
   * The entity storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  private $storageController;

  /**
   * Create a ContentEntityDatasource object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityManager $entity_manager, array $configuration, $plugin_id, array $plugin_definition) {
    // Initialize the parent chain of objects.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->storageController = $entity_manager->getStorageController($plugin_definition['entity_type']);
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * Get the entity storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageControllerInterface
   *   An instance of EntityStorageControllerInterface.
   */
  protected function getStorageController() {
    return $this->storageController;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $container->get('entity.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyInfo() { return array(); /* @todo */ }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    // Load the entity from the storage controller.
    $entity = $this->getStorageController()->load($id);
    // Wrap the entity into a datasource item.
    return $entity ? new EntityDatasourceItem($entity) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    // Initialize the items variable to an empty array.
    $items = array();
    // Iterate through the loaded entities.
    foreach ($this->getStorageController()->loadMultiple($ids) as $entity_id => $entity) {
      // Wrap the entity into a datasource item and add it to the list.
      $items[$entity_id] = new EntityDatasourceItem($entity);
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndexTracker(IndexInterface $index) {
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function hasIndexTracker(IndexInterface $index) {
    return FALSE; // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexTracker(IndexInterface $index) {
    return NULL; // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndexTracker(IndexInterface $index) {
    // @todo
  }

}
