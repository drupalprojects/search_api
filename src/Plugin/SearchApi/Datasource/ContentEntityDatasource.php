<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\field\FieldInstanceConfigInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
  protected $entityManager;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

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
    $this->storage = $entity_manager->getStorage($plugin_definition['entity_type']);
    $this->typedDataManager = \Drupal::typedDataManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var $entity_manager \Drupal\Core\Entity\EntityManager */
    $entity_manager = $container->get('entity.manager');

    return new static($entity_manager, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $type = $this->getEntityTypeId();
    $properties = $this->entityManager->getBaseFieldDefinitions($type);
    if ($bundles = $this->getIndexedBundles()) {
      foreach ($bundles as $bundle) {
        $properties += $this->entityManager->getFieldDefinitions($type, $bundle);
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    // Load the item by ID.
    $items = $this->loadMultiple(array($id));
    return ($items) ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $entity_ids = array();
    foreach ($ids as $item_id) {
      list($entity_id, $langcode) = explode(':', $item_id, 2);
      $entity_ids[$entity_id][$item_id] = $langcode;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $this->storage->loadMultiple(array_keys($entity_ids));
    $missing = array();
    $items = array();
    foreach ($entity_ids as $entity_id => $langcodes) {
      foreach ($langcodes as $item_id => $langcode) {
        if (!empty($entities[$entity_id]) && $entities[$entity_id]->hasTranslation($langcode)) {
          $items[$item_id] = $entities[$entity_id]->getTranslation($langcode);
        }
        else {
          $missing[] = $item_id;
        }
      }
    }
    // If we were unable to load some of the items, mark them as deleted.
    if ($missing) {
      $this->getIndex()->trackItemsDeleted($this->getPluginId(), array_keys($missing));
    }
    return $items;
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
        '#title' => $this->t('What should be indexed?'),
        '#options' => array(
          1 => $this->t('All except those selected'),
          0 => $this->t('None except those selected'),
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
  public function setConfiguration(array $new_config) {
    // Get the current configuration.
    $old_config = $this->getConfiguration();
    parent::setConfiguration($new_config);

    if (isset($new_config['default']) && isset($old_config['default'])) {
      // Apply the configuration changes.
      $bundles_start = array();
      $bundles_stop = array();
      // Check if the datasource configuration changed.
      if ($old_config['default'] != $new_config['default']) {
        // Invert the bundles so that the diff also resolves
        foreach ($old_config['bundles'] as $bundle_key => $bundle) {
          if ($bundle_key == $bundle) {
            $old_config['bundles'][$bundle_key] = 0;
          }
          else {
            $old_config['bundles'][$bundle_key] = $bundle_key;
          }
        }
      }

      if ((array_diff_assoc($new_config['bundles'], $old_config['bundles']))) {
         // StopTracking figures out which bundles to remove
        $diff = array_diff_assoc($new_config['bundles'], $old_config['bundles']);
        // A bundle is selected when the key equals its value
        foreach ($diff as $bundle_key => $bundle) {
          // Default is 0 for "Only those from the selected bundles"
          if ($new_config['default'] == 0) {
            if ($bundle_key === $bundle) {
              $bundles_start[$bundle_key] = $bundle;
            }
            else {
              $bundles_stop[$bundle_key] = $bundle;
            }
          }
          // Default is 1 for "All but those from one of the selected bundles"
          else {
            if ($bundle_key === $bundle) {
              $bundles_stop[$bundle_key] = $bundle;
            }
            else {
              $bundles_start[$bundle_key] = $bundle;
            }
          }
        }
        if (!empty($bundles_start)) {
          $this->startTrackingBundles(array_keys($bundles_start));
        }
        if (!empty($bundles_stop)) {
          $this->stopTrackingBundles(array_keys($bundles_stop));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    if ($item instanceof EntityInterface) {
      return $item->id() . ':' . $item->language();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item) {
    if ($item instanceof EntityInterface) {
      return $item->label();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item) {
    if ($item instanceof EntityInterface) {
      return $item->urlInfo();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemIds($limit = '-1', $from = NULL) {
    // @todo Implement paging.
    return $this->getBundleItemIds();
  }

  /**
   * {@inheritdoc}
   */
  public function startTrackingBundles(array $bundles) {
    // Check whether there are entities which need to be inserted.
    if (($entity_ids = $this->getBundleItemIds($bundles))) {
      // Register entities with the tracker.
      $this->getIndex()->trackItemsInserted($this->getPluginId(), $entity_ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function stopTrackingBundles(array $bundles) {
    // Check whether there are entities which need to be removed.
    if (($entity_ids = $this->getBundleItemIds($bundles))) {
      // Remove the items from the tracker.
      $this->getIndex()->trackItemsDeleted($this->getPluginId(), $entity_ids);
    }
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
   * {@inheritdoc}
   */
  public function getDatasourceType() {
    return $this->getEntityType()->id();
  }

  /**
   * Determine whether the entity type supports bundles.
   *
   * @return bool
   *   TRUE if the entity type supports bundles, otherwise FALSE.
   */
  public function isEntityBundlable() {
    // Get the entity type definition
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
   * Retrieves all item IDs of entities of specific bundles.
   *
   * @param array $bundles
   *   (optional) The bundles for which all item IDs should be returned.
   *   Defaults to all enabled bundles.
   *
   * @return array
   *   An array of all item IDs of these bundles.
   */
  public function getBundleItemIds(array $bundles = NULL) {
    $select = \Drupal::entityQuery($this->getEntityTypeId());

    // Get our bundles from the datasource if it was not passed.
    if (!isset($bundles)) {
      $bundles = $this->getIndexedBundles();
    }
    // If we have bundles to filter on
    if ($bundles) {
      if (count($bundles) != count($this->getEntityBundles())) {
        $select->condition($this->getEntityType()->getKey('bundle'), $bundles, 'IN');
      }
    }
    $entity_ids = $select->execute();

    $item_ids = array();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach (entity_load_multiple($this->getEntityTypeId(), $entity_ids) as $entity_id => $entity) {
      foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
        $item_ids[] = "$entity_id:$langcode";
      }
    }
    return $item_ids;
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
        if (isset($bundles[$config_bundle])) {
          unset($bundles[$config_bundle]);
        }
      }
    }
    // When the default is set to 0, all bundles that are selected are chosen
    else {
      // Remove all unselected bundles
      foreach ($bundles as $bundle_key => $bundle_value) {
        if ($bundle_value === 0) {
          unset($bundles[$bundle_key]);
        }
      }
    }
    return array_keys($bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes() {
    $view_modes = $this->entityManager->getViewModeOptions($this->getEntityTypeId());
    if (empty($view_modes)) {
      $view_modes = array('default' => t('Default'));
    }
    return $view_modes;
  }

  /**
   * {@inheritdoc}
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL) {
    if ($item instanceof EntityInterface) {
      $langcode = $langcode ?: $item->language()->id;
      return $this->entityManager->getViewBuilder($this->getEntityTypeId())->view($item, $view_mode, $langcode);
    }
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL) {
    try {
      $view_builder = $this->entityManager->getViewBuilder($this->getEntityTypeId());
      // Langcode passed, use that for viewing.
      if (isset($langcode)) {
        if (reset($items) instanceof EntityInterface) {
          return $view_builder->viewMultiple($items, $view_mode, $langcode);
        }
        return array();
      }
      // Otherwise, separate the items by language, keeping the keys.
      $items_by_language = array();
      foreach ($items as $i => $item) {
        if ($item instanceof EntityInterface) {
          $items_by_language[$item->language()->id][$i] = $item;
        }
      }
      // Then build the items for each language.
      $build = array();
      foreach ($items_by_language as $langcode => $language_items) {
        $build += $view_builder->viewMultiple($language_items, $view_mode, $langcode);
      }
      // Lastly, bring the viewed items into the correct order again.
      $ret = array();
      foreach ($items as $i => $item) {
        $ret[$i] = isset($build[$i]) ? $build[$i] : array();
      }
      return $ret;
    }
    catch (\Exception $e) {
      // The most common reason for this would be a
      // \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException in
      // getViewBuilder(), because the entity type definition doesn't specify a
      // view_builder class.
      return array();
    }
  }

  /**
   * Retrieves all indexes that are configured to index the given entity.
   *
   * @param ContentEntityInterface $entity
   *   The entity for which to check.
   *
   * @return \Drupal\search_api\Index\IndexInterface[]
   *   All indexes of this class that are configured to index the given entity.
   */
  public static function getIndexesForEntity(ContentEntityInterface $entity) {
    $datasource_id = 'entity:' . $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();

    $index_names = \Drupal::entityQuery('search_api_index')
      ->condition('datasourcePluginIds.*', $datasource_id)
      ->execute();

    if (!$index_names) {
      return array();
    }

    // Checks whether the indexes include the given entity's bundle.
    /** @var \Drupal\search_api\Index\IndexInterface[] $indexes */
    $indexes = \Drupal::entityManager()->getStorage('search_api_index')->loadMultiple($index_names);
    foreach ($indexes as $index_id => $index) {
      try {
        $config = $index->getDatasource($datasource_id)->getConfiguration();
        $default = !empty($config['default']);
        $bundle_set = !empty($config['bundles'][$entity_bundle]);
        if ($default == $bundle_set) {
          unset($indexes[$index_id]);
        }
      }
      catch (SearchApiException $e) {
        unset($indexes[$index_id]);
      }
    }

    return $indexes;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies += parent::calculateDependencies();

    $this->addDependency('module', $this->getEntityType()->getProvider());

    $fields = array();
    foreach ($this->getIndex()->getFields() as $field) {
      if ($field->getDatasourceId() === $this->pluginId) {
        $fields[] = $field->getPropertyPath();
      }
    }
    if ($field_dependencies = $this->getFieldDependencies($this->getEntityTypeId(), $fields)) {
      $this->addDependencies(array('entity' => $field_dependencies));
    }

    return $this->dependencies;
  }

  /**
   * Returns an array of config entity dependencies.
   *
   * @param string $entity_type_id
   *   The entity type to which these fields are attached.
   * @param string[] $fields
   *   An array of property paths on items of this entity type.
   *
   * @return string[]
   *   An array of IDs of entities on which this datasource depends.
   */
  protected function getFieldDependencies($entity_type_id, $fields) {
    $field_dependencies = array();

    // Figure out which fields are directly on the item and which need to be
    // extracted from nested items.
    $direct_fields = array();
    $nested_fields = array();
    foreach ($fields as $field) {
      if (strpos($field, ':entity:') !== FALSE) {
        list($direct, $nested) = explode(':entity:', $field, 2);
        $nested_fields[$direct][] = $nested;
      }
      elseif (strpos($field, ':') === FALSE) {
        $direct_fields[] = $field;
      }
    }

    // Extract the config dependency name for direct fields.
    foreach (array_keys($this->entityManager->getBundleInfo($entity_type_id)) as $bundle) {
      foreach ($this->entityManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $field_definition) {
        if ($field_definition instanceof FieldInstanceConfigInterface) {
          if (in_array($field_name, $direct_fields) || isset($nested_fields[$field_name])) {
            $field_dependencies[$field_definition->getConfigDependencyName()] = TRUE;
            $field_dependencies[$field_definition->getField()->getConfigDependencyName()] = TRUE;
          }

          // Recurse for nested fields.
          if (isset($nested_fields[$field_name])) {
            $entity_type = $field_definition->getSetting('target_type');
            $field_dependencies += $this->getFieldDependencies($entity_type, $nested_fields[$field_name]);
          }
        }
      }
    }

    return array_keys($field_dependencies);
  }

}
