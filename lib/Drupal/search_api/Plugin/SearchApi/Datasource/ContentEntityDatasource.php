<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\Component\Utility\String;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @SearchApiDatasource(
 *   id = "entity",
 *   name = @Translation("Content entity datasource"),
 *   description = @Translation("Exposes the content entities as datasource."),
 *   derivative = "Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasourceDerivative"
 * )
 */
class ContentEntityDatasource extends DatasourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $storage;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $databaseConnection;

  /**
   * Create a ContentEntityDatasource object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection to the database.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityManager $entity_manager, Connection $connection, array $configuration, $plugin_id, array $plugin_definition) {
    // Initialize the parent chain of objects.
    parent::__construct($connection, $configuration, $plugin_id, $plugin_definition);
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->storage = $entity_manager->getStorage($plugin_definition['entity_type']);
    $this->typedDataManager = \Drupal::typedDataManager();
    $this->databaseConnection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    /** @var $entity_manager \Drupal\Core\Entity\EntityManager */
    $entity_manager = $container->get('entity.manager');
    /** @var $connection \Drupal\Core\Database\Connection */
    $connection = $container->get('database');
    return new static($entity_manager, $connection, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Determine whether the index is valid for this datasource.
   *
   * @return boolean
   *   TRUE if the index is valid, otherwise FALSE.
   */
  protected function isValidIndex() {
    // Determine whether the index is compatible with the datasource.
    return $this->getIndex()->getDatasource()->getPluginId() == $this->getPluginId();
  }

  /**
   * Get the entity bundles which can be used in a select element.
   *
   * @return array
   *   An associative array of bundle labels, keyed by the bundle name.
   */
  protected function getEntityBundleOptions() {
    // Initialize the options variable to NULL.
    $options = array();
    // Try to retrieve the exposed entity type bundles.
    if (($bundles = $this->getEntityBundles())) {
      // Remove the default entity type bundle.
      unset($bundles[$this->getEntityTypeId()]);
      // Iterate through the bundles.
      foreach ($bundles as $bundle => $bundle_info) {
        // Add the bundle to the options list.
        $options[$bundle] = String::checkPlain($bundle_info['label']);
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    // Initialize the summary variable to an empty string.
    $summary = '';
    // Check if the entity supports bundles.
    if ($this->isEntityBundlable()) {
      // Get the configured bundles.
      $bundles = array_values(array_intersect_key($this->getEntityBundleOptions(), $this->configuration['bundles']));
      // Check in what operation the datasource is performing.
      if ($this->configuration['default'] == TRUE) {
        // Build the summary.
        $summary = $this->t('Excluded bundles: @bundles', array('@bundles' => implode(', ', $bundles)));
      }
      else {
        // Build the summary.
        $summary = $this->t('Included bundles: @bundles', array('@bundles' => implode(', ', $bundles)));
      }
    }
    return $summary;
  }

  /**
   * Returns the entity type id.
   *
   * @return string
   *   The entity type id.
   */
  public function getEntityTypeId() {
    // Get the plugin definition.
    $plugin_definition = $this->getPluginDefinition();
    // Get the entity type.
    return $plugin_definition['entity_type'];
  }

  /**
   * Returns the entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Entity type definition.
   */
  public function getEntityType() {
    return $this->entityManager->getDefinition($this->getEntityTypeId());
  }

  /**
   * Determine whether the entity type supports bundles.
   *
   * @return bool
   *   TRUE if the entity type supports bundles, otherwise FALSE.
   */
  public function isEntityBundlable() {
    // Get the entity type definition.
    $entity_type_definition = $this->entityManager->getDefinition($this->getEntityTypeId());
    // Determine whether the entity type supports bundles.
    return $entity_type_definition->hasKey('bundle');
  }

  /**
   * Get the entity bundles.
   *
   * @return array
   *   An associative array of bundle info, keyed by the bundle name.
   */
  public function getEntityBundles() {
    return $this->isEntityBundlable() ? $this->entityManager->getBundleInfo($this->getEntityTypeId()) : array();
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    /** @var \Drupal\Core\TypedData\ComplexDataDefinitionInterface $def */
    $def = $this->typedDataManager->createDataDefinition('entity:' . $this->getEntityTypeId());
    return $def->getPropertyDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    return $this->storage->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    // Check if the entity type supports bundles.
    if ($this->isEntityBundlable()) {
      // Get the entity type bundles.
      $bundles = $this->getEntityBundleOptions();
      // Build the default operation element.
      $form['default'] = array(
        '#type' => 'radios',
        '#title' => $this->t('Which items should be indexed?'),
        '#options' => array(
          0 => $this->t('Only those from the selected bundles'),
          1 => $this->t('All but those from one of the selected bundles'),
        ),
        '#default_value' => $this->configuration['default'],
      );
      // Build the bundle selection element.
      $form['bundles'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Bundles'),
        '#options' => $bundles,
        '#default_value' => $this->configuration['bundles'],
        '#size' => min(4, count($bundles)),
        '#multiple' => TRUE,
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'default' => 1,
      'bundles' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Get the current configuration.
    $current_configuration = $this->getConfiguration();
    // Apply the configuration changes.
    parent::setConfiguration($configuration);
    // Check if the datasource configuration changed.
    if ($current_configuration['default'] != $configuration['default'] || array_diff_key($current_configuration, $configuration) || array_diff_key($configuration, $current_configuration)) {
      // @todo: Needs to reindex.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl($item) {
    if ($item instanceof EntityInterface) {
      return $item->urlInfo();
    }
    return NULL;
  }

  /**
   * Starts tracking for this index.
   */
  public function startTracking() {
    $entity_ids = $this->getEntityIds();
    $this->trackInsert($entity_ids);
  }

  /**
   * Stops tracking for this index.
   */
  public function stopTracking() {
    $this->trackDelete();
  }

  /**
   * Gets the affected entity ids.
   */
  public function getEntityIds() {
    $node_ids = array();
    if ($bundles = $this->getIndexedBundles()) {
      $select = \Drupal::entityQuery($this->getEntityTypeId());
      if (count($bundles) != count($this->getEntityBundles())) {
        $select->condition($this->getEntityType()->getKey('bundle'), $bundles, 'IN');
      }
      $node_ids = $select->execute();
    }

    return $node_ids;
  }

  /**
   * Gets the indexed bundles
   */
  public function getIndexedBundles() {
    $configuration = $this->getConfiguration();
    $bundles = $configuration['bundles'];

    // When the default is set to 1, all bundles are selected except those
    // chosen
    if ($configuration['default']) {
      $bundles = $this->getEntityBundles();
      foreach ($configuration['bundles'] as $config_bundle_name => $config_bundle) {
        unset($bundles[$config_bundle_name]);
      }
    }
    return array_keys($bundles);
  }

}
