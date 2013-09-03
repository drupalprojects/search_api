<?php

/**
 * @file
 * Contains Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\QueryInterface;
use Drupal\search_api\Plugin\Type\Service\ServiceInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;

/**
 * Class representing a search server.
 *
 * @EntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   controllers = {
 *     "storage" = "Drupal\search_api\ServerStorageController",
 *     "access" = "Drupal\search_api\ServerAccessController",
 *     "form" = {
 *       "default" = "Drupal\search_api\ServerFormController",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteForm"
 *     }
 *   },
 *   config_prefix = "search_api.server",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "enabled"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search_api/server/{search_api_index}",
 *     "edit-form" = "/admin/config/search/search_api/server/{search_api_index}/edit",
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface {

  // Properties that will be set when object is loaded:

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
  public $name = '';

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
   * The id of the service class to use for this server.
   *
   * @var string
   */
  public $class = '';

  /**
   * The options used to configure the service object.
   *
   * @var array
   */
  public $options = array();

  /**
   * A flag indicating whether the server is enabled.
   *
   * @var integer
   */
  public $enabled = 1;

  /**
   * Plugin object for invoking service methods.
   *
   * @var \Drupal\search_api\Plugin\Type\Service\ServiceInterface
   */
  protected $plugin;

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
     'path' => 'admin/config/search/search_api/server/' . $this->id(),
      'options' => array(
        'entity_type' => $this->entityType,
        'entity' => $this,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    $this->ensurePlugin();
    if ($update) {
      if ($this->plugin->postUpdate()) {
        foreach ($this->getIndexes() as $index) {
          $index->reindex();
        }
      }
    }
    else {
      $this->plugin->postCreate();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    // Call the service classes' preDelete() hooks.
    foreach ($entities as $entity) {
      $entity->plugin->preDelete();
    }

    // Remove all indexes from the servers.
    $query = \Drupal::entityQuery('search_api_index');
    $query->condition('server', array_keys($entities), 'IN');
    foreach (search_api_index_load_multiple($query->execute()) as $index) {
      $index->server = NULL;
      $index->save();
    }

    // Remove tasks associated with the servers.
    $tasks = \Drupal::state()->get('search_api_tasks') ? : array();
    foreach ($entities as $server) {
      unset($tasks[$server->id()]);
    }
    \Drupal::state()->set('search_api_tasks', $tasks);
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexes(array $conditions = array()) {
    $query = \Drupal::entityQuery('search_api_index');
    $query->condition('server', $this->id());
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return search_api_index_load_multiple($query->execute());
  }

  /**
   * Implements the magic __sleep() method for controlling serialization.
   *
   * Serializes all properties except the plugin object.
   *
   * @return array
   *   An array of properties to be serialized.
   */
  public function __sleep() {
    $ret = get_object_vars($this);
    unset($ret['plugin']);
    return array_keys($ret);
  }

  /**
   * Implements the magic __call() method to pass on calls to the plugin object.
   *
   * If the service class defines additional methods, not specified in the
   * \Drupal\search_api\Plugin\Type\Service\ServiceInterface interface, then
   * they are called via this magic method.
   */
  public function __call($name, $arguments = array()) {
    $this->ensurePlugin();
    return call_user_func_array(array($this->plugin, $name), $arguments);
  }

  /**
   * Helper method for ensuring the plugin object is set up.
   */
  protected function ensurePlugin() {
    if (!isset($this->plugin)) {
      $class = search_api_get_service_info($this->class);
      if ($class && class_exists($class['class'])) {
        if (empty($this->options)) {
          // We always have to provide the options.
          $this->options = array();
        }
        $this->plugin = new $class['class']($this);
      }
      if (!($this->plugin instanceof ServiceInterface)) {
        throw new SearchApiException(t('Search server with machine name @name specifies illegal service class @class.', array('@name' => $this->id(), '@class' => $this->class)));
      }
    }
  }

  // Plugin methods

  // For increased clarity, and since some parameters are passed by reference,
  // we don't use the __call() magic method for those.

  public function configurationForm(array $form, array &$form_state) {
    $this->ensurePlugin();
    return $this->plugin->configurationForm($form, $form_state);
  }

  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    $this->ensurePlugin();
    return $this->plugin->configurationFormValidate($form, $values, $form_state);
  }

  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    $this->ensurePlugin();
    return $this->plugin->configurationFormSubmit($form, $values, $form_state);
  }

  public function supportsFeature($feature) {
    $this->ensurePlugin();
    return $this->plugin->supportsFeature($feature);
  }

  public function viewSettings() {
    $this->ensurePlugin();
    return $this->plugin->viewSettings();
  }

  public function addIndex(IndexInterface $index) {
    $this->ensurePlugin();
    return $this->plugin->addIndex($index);
  }

  public function postUpdate() {
    $this->ensurePlugin();
    return $this->plugin->postUpdate();
  }

  public function fieldsUpdated(IndexInterface $index) {
    $this->ensurePlugin();
    return $this->plugin->fieldsUpdated($index);
  }

  public function removeIndex($index) {
    $this->ensurePlugin();
    return $this->plugin->removeIndex($index);
  }

  public function indexItems(IndexInterface $index, array $items) {
    $this->ensurePlugin();
    return $this->plugin->indexItems($index, $items);
  }

  public function deleteItems($ids = 'all', IndexInterface $index = NULL) {
    $this->ensurePlugin();
    return $this->plugin->deleteItems($ids, $index);
  }

  public function search(QueryInterface $query) {
    $this->ensurePlugin();
    return $this->plugin->search($query);
  }

}
