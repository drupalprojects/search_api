<?php

/**
 * @file
 * Contains \Drupal\search_api\DataType\DataTypePluginManager.
 */

namespace Drupal\search_api\DataType;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api\Utility;

/**
 * Manages data type plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDataType
 * @see \Drupal\search_api\DataType\DataTypeInterface
 * @see \Drupal\search_api\DataType\DataTypePluginBase
 * @see plugin_api
 */
class DataTypePluginManager extends DefaultPluginManager {

  /**
   * Static cache for the custom data types.
   *
   * @var \Drupal\search_api\DataType\DataTypeInterface[]
   *
   * @see \Drupal\search_api\DataType\DataTypePluginManager::getCustomDataTypes()
   */
  protected $customDataTypes;

  /**
   * Static cache for the data type definitions.
   *
   * @var string[][]
   *
   * @see \Drupal\search_api\DataType\DataTypePluginManager::getDataTypeDefinitions()
   */
  protected $dataTypes;

  /**
   * Constructs a DataTypePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/search_api/data_type', $namespaces, $module_handler, 'Drupal\search_api\DataType\DataTypeInterface', 'Drupal\search_api\Annotation\SearchApiDataType');
    $this->setCacheBackend($cache_backend, 'search_api_data_type');
    $this->alterInfo('search_api_data_type_info');
  }

  /**
   * Returns the custom data types.
   *
   * @return \Drupal\search_api\DataType\DataTypeInterface[]
   *   An array of data type plugins, keyed by type identifier.
   */
  public function getCustomDataTypes() {
    if (!isset($this->customDataTypes)) {
      $this->customDataTypes = array();

      foreach ($this->getDefinitions() as $name => $data_type_definition) {
        if (class_exists($data_type_definition['class']) && empty($this->customDataTypes[$name])) {
          $data_type = $this->createInstance($name);
          $this->customDataTypes[$name] = $data_type;
        }
      }
    }

    return $this->customDataTypes;
  }

  /**
   * Returns either all data type definitions, or a specific one.
   *
   * @param string|null $type
   *   (optional) If specified, the type whose definition should be returned.
   *
   * @return string[][]|string[]|null
   *   If $type was not given, an array containing all data types. Otherwise,
   *   the definition for the given type, or NULL if it is unknown.
   *
   * @see \Drupal\search_api\Utility::getDefaultDataTypes()
   * @see \Drupal\search_api\DataTypePluginManager::getCustomDataTypes()
   */
  public function getDataTypeDefinitions($type = NULL) {
    if (!isset($this->dataTypes)) {
      $default_types = Utility::getDefaultDataTypes();
      $custom_data_types = [];

      foreach ($this->getCustomDataTypes() as $name => $custom_data_type) {
        $custom_data_types[$name] = array(
          'label' => $custom_data_type->label(),
          'description' => $custom_data_type->getDescription(),
          'fallback' => $custom_data_type->getFallbackType(),
        );
      }

      $this->dataTypes = array_merge($default_types, $custom_data_types);
    }
    if (isset($type)) {
      return isset($this->dataTypes[$type]) ? $this->dataTypes[$type] : NULL;
    }
    return $this->dataTypes;
  }

  /**
   * Returns all field data types known by the Search API as an options list.
   *
   * @return string[]
   *   An associative array with all recognized types as keys, mapped to their
   *   translated display names.
   *
   * @see \Drupal\search_api\DataTypePluginManager::getDataTypeDefinitions()
   */
  public function getDataTypeOptions() {
    $types = array();
    foreach ($this->getDataTypeDefinitions() as $id => $info) {
      $types[$id] = $info['label'];
    }

    return $types;
  }

}
