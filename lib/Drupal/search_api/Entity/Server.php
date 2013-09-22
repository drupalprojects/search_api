<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

/*
 * Include required classes and interfaces.
 */
use Drupal;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Server\ServerInterface;

/**
 * Class representing a search server.
 *
 * @EntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\search_api\Controller\ServerAccessController",
 *     "form" = {
 *       "default" = "Drupal\search_api\Controller\ServerFormController",
 *       "edit" = "Drupal\search_api\Controller\ServerFormController",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
 *       "enable" = "Drupal\search_api\Form\ServerEnableConfirmForm",
 *       "disable" = "Drupal\search_api\Form\ServerDisableConfirmForm"
 *     },
 *     "list" = "Drupal\search_api\Controller\ServerListController"
 *   },
 *   config_prefix = "search_api.server",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search_api/servers/{search_api_server}",
 *     "edit-form" = "/admin/config/search/search_api/servers/{search_api_server}/edit",
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
    $service_plugin_definition = Drupal::service('search_api.service.plugin.manager')->getDefinition($this->servicePluginId);
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
      $service_plugin_manager = Drupal::service('search_api.service.plugin.manager');
      // Create a service plugin instance.
      $this->servicePluginInstance = $service_plugin_manager->createInstance($service_plugin_id, $this->getServiceConfiguration());
    }
    return $this->servicePluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceConfiguration() {
    return $this->servicePluginConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function setServiceConfiguration(array $configuration) {
    // Set the service configuration.
    $this->servicePluginConfig = $configuration;
    // Clear the service instance cache.
    $this->servicePluginInstance = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageControllerInterface $storage_controller) {
    // Perform default entity pre save.
    parent::preSave($storage_controller);
    // Check if the service is valid.
    if ($this->hasValidService()) {
      // Check if the entity is being created.
      if ($this->isNew()) {
        // Notify the service about the instance configuration being created.
        $this->getService()->preInstanceConfigurationCreate();
      }
      else {
        // Notify the service about the instance configuration being updated.
        $this->getService()->preInstanceConfigurationUpdate();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    // Perform default entity post save.
    parent::postSave($storage_controller, $update);
    // Check if the service is valid.
    if ($this->hasValidService()) {
      // Check if the entity is was updated.
      if ($update) {
        // Notify the service about the instance configuration that was updated.
        $this->getService()->postInstanceConfigurationUpdate();
      }
      else {
        // Notify the service about the instance configuration that was created.
        $this->getService()->postInstanceConfigurationCreate();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    // Perform default entity pre delete.
    parent::preDelete($storage_controller, $entities);
    // Iterate through the entities.
    foreach ($entities as $entity) {
      // Check if the service is valid.
      if ($entity->hasValidService()) {
        // Notify the service about the instance configuration being deleted.
        $entity->getService()->preInstanceConfigurationDelete();
      }
    }
    // Get the indexes associated with the servers.
    $index_ids = Drupal::entityQuery('search_api_index')
      ->condition('serverMachineName', array_keys($entities), 'IN')
      ->execute();
    // Load the related indexes.
    $indexes = Drupal::entityManager()->getStorageController('search_api_index')->loadMultiple($index_ids);
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
  public static function postDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    // Perform default entity post delete.
    parent::postDelete($storage_controller, $entities);
    // Iterate through the entities.
    foreach ($entities as $entity) {
      // Check if the service is valid.
      if ($entity->hasValidService()) {
        // Notify the service about the instance configuration that was deleted.
        $entity->getService()->postInstanceConfigurationDelete();
      }
    }
  }

}