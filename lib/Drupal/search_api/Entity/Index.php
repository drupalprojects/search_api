<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Index\IndexInterface;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\search_api\Controller\IndexAccessController",
 *     "list_builder" = "Drupal\search_api\Controller\IndexListController",
 *     "form" = {
 *       "default" = "Drupal\search_api\Controller\IndexFormController",
 *       "edit" = "Drupal\search_api\Controller\IndexFormController",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "enable" = "Drupal\search_api\Form\IndexEnableConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm"
 *     },
 *     "list" = "Drupal\search_api\Controller\IndexListController"
 *   },
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "search_api.index_edit",
 *     "add-form" = "search_api.index_add",
 *     "edit-form" = "search_api.index_edit_default",
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
   * - fields: An array of all indexed fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: (optional) A boost value for terms found in this field during
   *     searches. Usually only relevant for fulltext fields. Defaults to 1.0.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then just contain the primitive data type
   *     of the ID field, meaning that servers will ignore this and merely index
   *     the entity's ID. Components displaying this field, though, are advised
   *     to use the entity label instead of the ID.
   * - additional fields: An associative array with keys and values being the
   *   field identifiers of related entities whose fields should be displayed.
   * - data_alter_callbacks: An array of all data alterations available. Keys
   *   are the alteration identifiers, the values are arrays containing the
   *   settings for that data alteration. The inner structure looks like this:
   *   - status: Boolean indicating whether the data alteration is enabled.
   *   - weight: Used for sorting the data alterations.
   *   - settings: Alteration-specific settings, configured via the alteration's
   *     configuration form.
   * - processors: An array of all processors available for the index. The keys
   *   are the processor identifiers, the values are arrays containing the
   *   settings for that processor. The inner structure looks like this:
   *   - status: Boolean indicating whether the processor is enabled.
   *   - weight: Used for sorting the processors.
   *   - settings: Processor-specific settings, configured via the processor's
   *     configuration form.
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
  protected $datasourcePluginInstance;

  /**
   * The machine name of the server on which data should be indexed.
   *
   * @var string
   */
  public $serverMachineName;

  /**
   * The server object instance.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * Clones an index object.
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

}
