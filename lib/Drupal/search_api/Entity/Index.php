<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

/*
 * Include required classes and interfaces.
 */
use Drupal;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Exception\SearchApiException;

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
    // Prevent the server instance from being cloned.
    $this->server = NULL;
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
  public function hasValidServer() {
    return Drupal::entityManager()->getStorageController('search_api_server')->load($this->serverMachineName) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    // Check if the server needs to be resolved.
    if (!$this->server) {
      // Get the server machine name.
      $server_machine_name = $this->serverMachineName;
      // Get the server from the storage.
      $this->server = Drupal::entityManager()->getStorageController('search_api_server')->load($server_machine_name);
      // Check if the server was not resolved.
      if (!$this->server) {
        // Raise SearchApiException: invalid or missing server.
        throw new SearchApiException(format_string('Search index with machine name @name specifies an illegal server @server', array('@name' => $this->id(), '@server' => $server_machine_name)));
      }
    }
    return $this->server;
  }

}