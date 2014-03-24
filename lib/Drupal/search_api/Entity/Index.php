<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Index\IndexInterface;

/**
 * Class representing a search index.
 *
 * @EntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search server"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\search_api\Controller\IndexAccessController",
 *     "form" = {
 *       "default" = "Drupal\search_api\Controller\IndexFormController",
 *       "edit" = "Drupal\search_api\Controller\IndexFormController",
 *       "fields" = "Drupal\search_api\Controller\IndexFieldsFormController",
 *       "workflow" = "Drupal\search_api\Controller\IndexWorkflowFormController",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "enable" = "Drupal\search_api\Form\IndexEnableConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm"
 *     },
 *     "list" = "Drupal\search_api\Controller\IndexListController"
 *   },
 *   config_prefix = "search_api.index",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search_api/indexes/{search_api_index}",
 *     "edit-form" = "/admin/config/search/search_api/indexes/{search_api_index}/edit",
 *   }
 * )
 */
class Index extends ConfigEntityBase implements IndexInterface {

  /**
   * The machine name of the index.
   *
   * @var string
   */
  public $machine_name;

  /**
   * A name to be displayed for the index.
   *
   * @var string
   */
  public $name;

  /**
   * A Universally Unique Identifier for the index.
   *
   * @var string
   */
  public $uuid;

  /**
   * A string describing the index' use to users.
   *
   * @var string
   */
  public $description;

  /**
   * A flag indicating whether to write to this index.
   *
   * @var integer
   */
  public $readOnly = FALSE;

  /**
   * An array of options for configuring this index. The layout is as follows:
   * - cron_limit: The maximum number of items to be indexed per cron batch.
   * - index_directly: Boolean setting whether entities are indexed immediately
   *   after they are created or updated.
   *
   * @var array
   */
  public $options = array(
    'cron_limit' => SEARCH_API_DEFAULT_CRON_LIMIT,
    'index_directly' => FALSE,
  );

  /**
   * The datasource plugin ID.
   *
   * @var string
   */
  public $datasourcePluginId;

  /**
   * The datasource plugin configuration.
   *
   * @var array
   */
  public $datasourcePluginConfig = array();

  /**
   * The datasource plugin instance.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  private $datasourcePluginInstance;

  /**
   * The machine name of the server which data should be indexed.
   *
   * @var string
   */
  public $serverMachineName;

  /**
   * The server object instance.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  private $server;

  /**
   * Clone a Server object.
   */
  public function __clone() {
    // Prevent the datasource and server instance from being cloned.
    $this->datasourcePluginInstance = $this->server = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->machine_name;
  }

  /**
   * {@inheritdoc}
   */
  public function uri() {
    return array(
     'path' => 'admin/config/search/search_api/indexes/' . $this->id(),
      'options' => array(
        'entity_type' => $this->entityType,
        'entity' => $this,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->readOnly;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name) {
    // Get the options.
    $options = $this->getOptions();
    // Get the option value for the given key.
    return isset($options[$name]) ? $options[$name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidDatasource() {
    // Get the datasource plugin definition.
    $datasource_plugin_definition = Drupal::service('search_api.datasource.plugin.manager')->getDefinition($this->datasourcePluginId);
    // Determine whether the datasource is valid.
    return !empty($datasource_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource() {
    // Check if the datasource plugin instance needs to be resolved.
    if (!$this->datasourcePluginInstance && $this->hasValidDatasource()) {
      // Get the ID of the datasource plugin.
      $datasource_plugin_id = $this->datasourcePluginId;
      // Get the datasource plugin manager.
      $datasource_plugin_manager = Drupal::service('search_api.datasource.plugin.manager');
      // Get the plugin configuration for the datasource.
      $datasource_plugin_configuration = array('_index_' => $this) + $this->datasourcePluginConfig;
      // Create a datasource plugin instance.
      $this->datasourcePluginInstance = $datasource_plugin_manager->createInstance($datasource_plugin_id, $datasource_plugin_configuration);
    }
    return $this->datasourcePluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidServer() {
    return $this->serverMachineName !== NULL && Drupal::entityManager()->getStorageController('search_api_server')->load($this->serverMachineName) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    // Check if the server needs to be resolved. Note we do not use
    // hasValidServer to prevent duplicate load calls to the storage controller.
    if (!$this->server) {
      // Get the server machine name.
      $server_machine_name = $this->serverMachineName;
      // Get the server from the storage.
      $this->server = Drupal::entityManager()->getStorageController('search_api_server')->load($server_machine_name);
    }
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server = NULL) {
    // Overwrite the current server instance.
    $this->server = $server;
    // Overwrite the server machine name.
    $this->serverMachineName = $server ? $server->id() : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getExportProperties() {
    // Get the exported properties.
    $properties = parent::getExportProperties();
    // Check if the datasource is valid.
    if ($this->hasValidDatasource()) {
      // Overwrite the datasource plugin configuration with the active.
      $properties['datasourcePluginConfig'] = $this->getDatasource()->getConfiguration();
    }
    else {
      // Clear the datasource plugin configuration.
      $properties['datasourcePluginConfig'] = array();
    }
    return $properties;
  }

}
