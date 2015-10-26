<?php

/**
 * @file
 * Contains \Drupal\search_api\DataType\DataTypePluginBase.
 */

namespace Drupal\search_api\DataType;

use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Backend\BackendPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other data type classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_tracker_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the data type class.
 * - label: The human-readable name of the data type class, translated.
 * - description: A human-readable description for the data type class,
 *   translated.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @SearchApiDataType(
 *   id = "my_data_type",
 *   label = @Translation("My data type"),
 *   description = @Translation("Some information about my data type")
 * )
 * @endcode
 *
 * Search API comes with a couple of default datatypes. These have an extra
 * "default" property in the annotation. It is not allowed for custom data type
 * plugins to set this property.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypePluginManager
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see plugin_api
 */
abstract class DataTypePluginBase extends ConfigurablePluginBase implements DataTypeInterface {

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\search_api\Backend\BackendPluginManager|null
   */
  protected $backendManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    // Since defaultConfiguration() depends on the plugin definition, we need to
    // override the constructor and set the definition property before calling
    // that method.
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $data_type */
    $data_type = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\search_api\Backend\BackendPluginManager $backend_manager */
    $backend_manager = $container->get('plugin.manager.search_api.backend');
    $data_type->setBackendManager($backend_manager);

    return $data_type;
  }

  /**
   * Retrieves the backend plugin manager.
   *
   * @return \Drupal\search_api\Backend\BackendPluginManager
   *   The backend plugin manager.
   */
  public function getBackendManager() {
    return $this->backendManager ?: \Drupal::service('plugin.manager.search_api.backend');
  }

  /**
   * Sets the backend plugin manager.
   *
   * @param \Drupal\search_api\Backend\BackendPluginManager $backend_manager
   *   The backend plugin manager.
   *
   * @return $this
   */
  public function setBackendManager(BackendPluginManager $backend_manager) {
    $this->backendManager = $backend_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackType() {
    return 'text';
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault() {
    return !empty($this->pluginDefinition['default']);
  }

}
