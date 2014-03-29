<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "access" = "Drupal\search_api\Handler\ServerAccessHandler",
 *     "list_builder" = "Drupal\search_api\OverviewListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\ServerForm",
 *       "edit" = "Drupal\search_api\Form\ServerForm",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
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
 *     "delete-form" = "search_api.server_delete",
 *     "disable" = "search_api.server_disable",
 *     "enable" = "search_api.server_enable",
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface, PluginFormInterface {

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
      $config = $this->servicePluginConfig;
      $config['server'] = $this;
      $this->servicePluginInstance = $service_plugin_manager->createInstance($service_plugin_id, $config);
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
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // Perform default entity pre delete.
    parent::preDelete($storage, $entities);
    // Get the indexes associated with the servers.
    $index_ids = \Drupal::entityQuery('search_api_index')
      ->condition('serverMachineName', array_keys($entities), 'IN')
      ->execute();
    // Load the related indexes.
    $indexes = \Drupal::entityManager()->getStorage('search_api_index')->loadMultiple($index_ids);
    // Iterate through the indexes.
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\Index\IndexInterface $index */
      // Remove the index from the server.
      $index->setServer(NULL);
      $index->setStatus(FALSE);
      // Save changes made to the index.
      $index->save();
    }

    // Iterate through the servers, executing the service's preDelete() methods.
    foreach ($entities as $server) {
      /** @var \Drupal\search_api\Server\ServerInterface $server */
      // Remove the index from the server.
      $service = $server->getService();
      if (!empty($service)) {
        $server->getService()->preDelete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    return $this->getService()->buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    return $this->getService()->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    return $this->getService()->submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return $this->getService()->viewSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return $this->getService()->supportsFeature($feature);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDatatype($type) {
    return $this->getService()->supportsDatatype($type);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $service = $this->getService();
    if (!empty($service)) {
      if ($update) {
        return $service->postUpdate();
      }
      else {
        return $service->postInsert();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexes() {
    // Get the index storage.
    $storage = \Drupal::entityManager()->getStorage('search_api_index');
    // Retrieve the indexes attached to the server.
    return $storage->loadByProperties(array(
          'serverMachineName' => $this->id(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    try {
      $this->getService()->addIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while adding index %index to server %server: !message in %function (line %line of %file).', $vars);
      $this->tasksAdd(__FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    try {
      if ($this->getService()->updateIndex($index)) {
        $index->reindex();
        return TRUE;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while updating the fields of index %index on server %server: !message in %function (line %line of %file).', $vars);
      $this->tasksAdd(__FUNCTION__, $index, isset($index->original) ? $index->original : NULL);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex(IndexInterface $index) {
    // When removing an index from a server, it doesn't make any sense anymore to
    // delete items from it, or react to other changes.
    $this->tasksDelete(NULL, $index);

    try {
      $this->getService()->removeIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => is_object($index) ? $index->label() : $index,
      );
      watchdog_exception('search_api', $e, '%type while removing index %index from server %server: !message in %function (line %line of %file).', $vars);
      $this->tasksAdd(__FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    return $this->getService()->indexItems($index, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index = NULL, array $ids) {
    try {
      $this->getService()->deleteItems($index, $ids);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: !message in %function (line %line of %file).', $vars);
      $this->tasksAdd(__FUNCTION__, $index, $ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems(IndexInterface $index = NULL) {
    try {
      $this->getService()->deleteAllItems($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: !message in %function (line %line of %file).', $vars);
      $this->tasksAdd(__FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    return $this->getService()->search($query);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Check if the server is disabled.
    if (!$this->status()) {
      // Disable all the indexes that belong to this server
      foreach ($this->getIndexes() as $index) {
        /** @var $index \Drupal\search_api\Entity\Index */
        $index->setStatus(FALSE)->save();
      }
    }
  }

  /**
   * @param array $servicePluginConfig
   */
  public function setServicePluginConfig($servicePluginConfig) {
    $this->servicePluginConfig = $servicePluginConfig;
  }

  /**
   * @return array
   */
  public function getServicePluginConfig() {
    return $this->servicePluginConfig;
  }

  /**
   * Adds an entry into a server's list of pending tasks.
   *
   * @param $type
   *   The type of task to perform.
   * @param \Drupal\search_api\Index\IndexInterface|string|null $index
   *   (optional) If applicable, the index to which the task pertains (or its
   *   machine name).
   * @param mixed $data
   *   (optional) If applicable, some further data necessary for the task.
   */
  public function tasksAdd($type, $index = NULL, $data = NULL) {
    db_insert('search_api_task')
      ->fields(array(
        'server_id' => $this->id(),
        'type' => $type,
        'index_id' => $index ? (is_object($index) ? $index->id() : $index) : NULL,
        'data' => isset($data) ? serialize($data) : NULL,
      ))
      ->execute();
  }

  /**
   * Removes pending server tasks from the list.
   *
   * @param array|null $ids
   *   (optional) The IDs of the pending server tasks to delete. Set to NULL
   *   to not filter by IDs.
   * @param \Drupal\search_api\Index\IndexInterface|string|null $index
   *   (optional) An index (or its machine name) for which the tasks should be
   *   deleted. Set to NULL to delete tasks for all indexes.
   */
  public function tasksDelete(array $ids = NULL, $index = NULL) {
    $delete = db_delete('search_api_task');
    if ($ids) {
      $delete->condition('id', $ids);
    }
    if ($server) {
      $delete->condition('server_id', $this->id());
    }
    if ($index) {
      $delete->condition('index_id', is_object($index) ? $this->id() : $index);
    }
    $delete->execute();
  }
}
