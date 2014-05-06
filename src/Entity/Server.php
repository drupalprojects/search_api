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
 *     "list_builder" = "Drupal\search_api\OverviewListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\ServerForm",
 *       "edit" = "Drupal\search_api\Form\ServerForm",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\ServerDisableConfirmForm",
 *       "clear" = "Drupal\search_api\Form\ServerClearConfirmForm"
 *     },
 *   },
 *   admin_permission = "administer search_api",
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
 *     "enable" = "search_api.server_enable"
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
   * The ID of the backend plugin.
   *
   * @var string
   */
  public $backendPluginId;

  /**
   * The backend plugin configuration.
   *
   * @var array
   */
  public $backendPluginConfig = array();

  /**
   * The backend plugin instance.
   *
   * @var \Drupal\search_api\Backend\BackendInterface
   */
  private $backendPluginInstance = NULL;

  /**
   * Clone a Server object.
   */
  public function __clone() {
    // Prevent the backend plugin instance from being cloned.
    $this->backendPluginInstance = NULL;
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
  public function hasValidBackend() {
    // Get the backend plugin definition.
    $backend_plugin_definition = \Drupal::service('plugin.manager.search_api.backend')->getDefinition($this->backendPluginId);
    // Determine whether the backend is valid.
    return !empty($backend_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendId() {
    return $this->backendPluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackend() {
    // Check if the backend plugin instance needs to be resolved.
    if (!$this->backendPluginInstance) {
      // Get the backend plugin manager.
      $backend_plugin_manager = \Drupal::service('plugin.manager.search_api.backend');

      // Try to create a backend plugin instance.
      $config = $this->backendPluginConfig;
      $config['server'] = $this;
      if (!($this->backendPluginInstance = $backend_plugin_manager->createInstance($this->backendPluginId, $config))) {
        $args['@backend'] = $this->backendPluginId;
        $args['%server'] = $this->label();
        throw new SearchApiException(t('The backend with ID "@backend" could not be retrieved for server %server.', $args));
      }
    }
    return $this->backendPluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // Get the exported properties.
    $properties = parent::toArray();
    // Check if the backend is valid.
    if ($this->hasValidBackend()) {
      // Overwrite the backend plugin configuration with the active.
      $properties['backendPluginConfig'] = $this->getBackend()->getConfiguration();
    }
    else {
      // Clear the backend plugin configuration.
      $properties['backendPluginConfig'] = array();
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

    // Iterate through the servers, executing the backend's preDelete() methods.
    foreach ($entities as $server) {
      /** @var \Drupal\search_api\Server\ServerInterface $server */
      // Remove the index from the server.
      if ($server->hasValidBackend()) {
        $server->getBackend()->preDelete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    return $this->hasValidBackend() ? $this->getBackend()->buildConfigurationForm($form, $form_state) : array();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    if ($this->hasValidBackend()) {
      $this->getBackend()->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    if ($this->hasValidBackend()) {
      $this->getBackend()->submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return $this->hasValidBackend() ? $this->getBackend()->viewSettings() : array();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return $this->hasValidBackend() ? $this->getBackend()->supportsFeature($feature) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDatatype($type) {
    return $this->hasValidBackend() ? $this->getBackend()->supportsDatatype($type) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($this->hasValidBackend()) {
      if ($update) {
        $this->getBackend()->postUpdate();
      }
      else {
        $this->getBackend()->postInsert();
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
      $this->getBackend()->addIndex($index);
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
      $this->getBackend()->updateIndex($index);
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
      $this->getBackend()->removeIndex($index);
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
    return $this->getBackend()->indexItems($index, $items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index = NULL, array $ids) {
    try {
      $this->getBackend()->deleteItems($index, $ids);
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
      $this->getBackend()->deleteAllItems($index);
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
    return $this->getBackend()->search($query);
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
   * {@inheritdoc}
   */
  public function setBackendPluginConfig($backendPluginConfig) {
    $this->backendPluginConfig = $backendPluginConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendPluginConfig() {
    return $this->backendPluginConfig;
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
   * {@inheritdoc}
   */
  public function tasksDelete(array $ids = NULL, $index = NULL) {
    $delete = db_delete('search_api_task');
    $delete->condition('server_id', $this->id());
    if ($ids) {
      $delete->condition('id', $ids);
    }
    if ($index) {
      $delete->condition('index_id', is_object($index) ? $this->id() : $index);
    }
    $delete->execute();
  }
}
