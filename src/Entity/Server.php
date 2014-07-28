<?php

/**
 * @file
 * Contains \Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Utility\Utility;

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
  protected $backend;

  /**
   * The backend plugin configuration.
   *
   * @var array
   */
  protected $backend_config = array();

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
    $backend_plugin_definition = \Drupal::service('plugin.manager.search_api.backend')->getDefinition($this->getBackendId(), FALSE);
    // Determine whether the backend is valid.
    return !empty($backend_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendId() {
    return $this->backend;
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
      $config = $this->backend_config;
      $config['server'] = $this;
      if (!($this->backendPluginInstance = $backend_plugin_manager->createInstance($this->getBackendId(), $config))) {
        $args['@backend'] = $this->getBackendId();
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
      $properties['backend_config'] = $this->getBackend()->getConfiguration();
    }
    else {
      // Clear the backend plugin configuration.
      $properties['backend_config'] = array();
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
      ->condition('server', array_keys($entities), 'IN')
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
      // Execute the backend's preDelete() hook method.
      if ($server->hasValidBackend()) {
        $server->getBackend()->preDelete();
      }
      // Delete all remaining tasks for the server.
      Utility::getServerTaskManager()->delete(NULL, $server);
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
  public function getIndexes(array $properties = array()) {
    // Get the index storage.
    $storage = \Drupal::entityManager()->getStorage('search_api_index');
    // Retrieve the indexes attached to the server.
    return $storage->loadByProperties(array(
      'server' => $this->id(),
    ) + $properties);
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $server_task_manager = Utility::getServerTaskManager();
    // When freshly adding an index to a server, it doesn't make any sense
    // to execute possible other tasks for that server/index combination.
    // (removeIndex() is implicit when adding an index which was already added.)
    $server_task_manager->delete(NULL, $this, $index);

    try {
      $this->getBackend()->addIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while adding index %index to server %server: !message in %function (line %line of %file).', $vars);
      $server_task_manager->add($this, __FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        return $this->getBackend()->updateIndex($index);
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while updating the fields of index %index on server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index, isset($index->original) ? $index->original : NULL);
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $server_task_manager = Utility::getServerTaskManager();
    // When removing an index from a server, it doesn't make any sense anymore
    // to delete items from it, or react to other changes.
    $server_task_manager->delete(NULL, $this, $index);

    try {
      $this->getBackend()->removeIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => is_object($index) ? $index->label() : $index,
      );
      watchdog_exception('search_api', $e, '%type while removing index %index from server %server: !message in %function (line %line of %file).', $vars);
      $server_task_manager->add($this, __FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $server_task_manager = Utility::getServerTaskManager();
    if ($server_task_manager->execute($this)) {
      return $this->getBackend()->indexItems($index, $items);
    }
    throw new SearchApiException(t('Could not index items because pending server tasks could not be executed.'));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    if ($index->isReadOnly()) {
      $vars = array(
        '%index' => $index->label(),
      );
      watchdog('search_api', 'Trying to delete items of index %index which is marked as read-only.', $vars, WATCHDOG_WARNING);
      return;
    }

    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteItems($index, $ids);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index, $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index) {
    if ($index->isReadOnly()) {
      $vars = array(
        '%index' => $index->label(),
      );
      watchdog('search_api', 'Trying to delete items of index %index which is marked as read-only.', $vars, WATCHDOG_WARNING);
      return;
    }

    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteAllIndexItems($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items of index %index from server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems() {
    $failed = array();
    $properties['read_only'] = FALSE;
    foreach ($this->getIndexes($properties) as $index) {
      try {
        $this->getBackend()->deleteAllIndexItems($index);
      }
      catch (SearchApiException $e) {
        $failed[] = $index->label();
      }
    }
    if (!empty($e)) {
      $args['%server'] = $this->label();
      $args['@indexes'] = implode(', ', $failed);
      $message = String::format('Deleting all items from server %server failed for the following (write-enabled) indexes: @indexes.', $args);
      throw new SearchApiException($message, 0, $e);
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
  public function getBackendConfig() {
    return $this->backend_config;
  }

  /**
   * {@inheritdoc}
   */
  public function setBackendConfig(array $backend_config) {
    $this->backend_config = $backend_config;
    $this->getBackend()->setConfiguration($backend_config);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add a dependency on the module that provides the backend for this server.
    if ($this->hasValidBackend() && ($backend = $this->getBackend())) {
      $this->addDependency('module', $backend->getPluginDefinition()['provider']);
    }

    return $this->dependencies;
  }

}
