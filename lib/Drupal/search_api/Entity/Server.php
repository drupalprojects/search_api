<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Service\ServiceInterface;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\search_api\Handler\ServerAccessHandler",
 *     "list_builder" = "Drupal\search_api\OverviewListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\ServerForm",
 *       "edit" = "Drupal\search_api\Form\ServerForm",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
 *       "enable" = "Drupal\search_api\Form\ServerEnableConfirmForm",
 *       "disable" = "Drupal\search_api\Form\ServerDisableConfirmForm"
 *     },
 *   },
 *   config_prefix = "server",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "search_api.server_view",
 *     "add-form" = "search_api.server_add",
 *     "edit-form" = "search_api.server_edit",
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface {

  /**
   * The machine name of the server.
   *
   * @var string
   */
  public $machine_name;

  /**
   * The displayed name for a server.
   *
   * @var string
   */
  public $name;

  /**
   * The server UUID.
   *
   * @var string
   */
  public $uuid;

  /**
   * The displayed description for a server.
   *
   * @var string
   */
  public $description = '';

  /**
   * The ID of the service plugin.
   *
   * @var string
   */
  public $servicePluginId;

  /**
   * The service plugin configuration.
   *
   * @var array
   */
  public $servicePluginConfig = array();

  /**
   * The service plugin instance.
   *
   * @var \Drupal\search_api\Service\ServiceInterface
   */
  private $servicePluginInstance = NULL;

  /**
   * Clone a Server object.
   */
  public function __clone() {
    // Prevent the service plugin instance from being cloned.
    $this->servicePluginInstance = NULL;
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
     'path' => 'admin/config/search/search_api/servers/' . $this->id(),
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
  public function hasValidService() {
    // Get the service plugin definition.
    $service_plugin_definition = \Drupal::service('search_api.service.plugin.manager')->getDefinition($this->servicePluginId);
    // Determine whether the service is valid.
    return !empty($service_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getService() {
    // Check if the service plugin instance needs to be resolved.
    if (!$this->servicePluginInstance && $this->hasValidService()) {
      // Get the ID of the service plugin.
      $service_plugin_id = $this->servicePluginId;
      // Get the service plugin manager.
      $service_plugin_manager = \Drupal::service('search_api.service.plugin.manager');
      // Create a service plugin instance.
      $this->servicePluginInstance = $service_plugin_manager->createInstance($service_plugin_id, $this->servicePluginConfig);
    }
    return $this->servicePluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // Get the exported properties.
    $properties = parent::toArray();
    // Check if the service is valid.
    if ($this->hasValidService()) {
      // Overwrite the service plugin configuration with the active.
      $properties['servicePluginConfig'] = $this->getService()->getConfiguration();
    }
    else {
      // Clear the service plugin configuration.
      $properties['servicePluginConfig'] = array();
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    // Perform default entity pre delete.
    parent::preDelete($storage_controller, $entities);
    // Get the indexes associated with the servers.
    $index_ids = \Drupal::entityQuery('search_api_index')
      ->condition('serverMachineName', array_keys($entities), 'IN')
      ->execute();
    // Load the related indexes.
    $indexes = \Drupal::entityManager()->getStorageController('search_api_index')->loadMultiple($index_ids);
    // Iterate through the indexes.
    foreach ($indexes as $index) {
      // Remove the index from the server.
      $index->setServer(NULL);
      // Save changes made to the index.
      $index->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $this->ensurePlugin();
    return $this->servicePluginInstance->search($query);
  }

  /**
   * Helper method for ensuring the proxy object is set up.
   */
  protected function ensurePlugin() {
    if (!isset($this->servicePluginInstance)) {
      if (!($this->getService() instanceof ServiceInterface)) {
        throw new SearchApiException(t('Search server with machine name @name specifies illegal service plugin @plugin.', array('@name' => $this->machine_name, '@plugin' => $this->servicePluginId)));
      }
    }
  }

}
