<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api\Datasource\Entity\EntityItem;
use Drupal\search_api\Datasource\Tracker\DefaultTracker;
use Drupal\Component\Utility\String;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @SearchApiDatasource(
 *   id = "search_api_content_entity_datasource",
 *   name = @Translation("Content entity datasource"),
 *   description = @Translation("Exposes the content entities as datasource."),
 *   derivative = "Drupal\search_api\Plugin\SearchApi\Datasource\Derivative\ContentEntityDatasourceDerivative"
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $databaseConnection;

  /**
   * Cache which contains already loaded TrackerInterface object.
   *
   * @var \Drupal\search_api\Datasource\Tracker\TrackerInterface
   */
  private $tracker;

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
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->storageController = $entity_manager->getStorageController($plugin_definition['entity_type']);
    $this->databaseConnection = $connection;
    $this->tracker = new DefaultTracker($this->getIndex(), $connection);
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
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   An instance of Connection.
   */
  protected function getDatabaseConnection() {
    return $this->databaseConnection;
  }

  /**
   * Get the tracker.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerInterface
   *   An instance of TrackerInterface.
   */
  protected function getTracker() {
    return $this->tracker;
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
      unset($bundles[$this->getEntityType()]);
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
   * Get the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType() {
    // Get the plugin definition.
    $plugin_definition = $this->getPluginDefinition();
    // Get the entity type.
    return $plugin_definition['entity_type'];
  }

  /**
   * Determine whether the entity type supports bundles.
   *
   * @return bool
   *   TRUE if the entity type supports bundles, otherwise FALSE.
   */
  public function isEntityBundlable() {
    // Get the entity type definition.
    $entity_type_definition = $this->getEntityManager()->getDefinition($this->getEntityType());
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
    return $this->isEntityBundlable() ? $this->getEntityManager()->getBundleInfo($this->getEntityType()) : array();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    return new static(
      $container->get('entity.manager'),
      $container->get('database'),
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
    return $entity ? new EntityItem($entity) : NULL;
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
      $items[$entity_id] = new EntityItem($entity);
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->getTracker()->getStatus();
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
        '#type' => 'select',
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
  public function submitConfigurationForm(array &$form, array &$form_state) {
    // Apply the configuration.
    $this->setConfiguration(array(
      'default' => $form_state['values']['datasourcePluginConfig']['default'],
      'bundles' => $form_state['values']['datasourcePluginConfig']['bundles'],
    ));
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

}
