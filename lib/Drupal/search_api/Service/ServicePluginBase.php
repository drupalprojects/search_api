<?php

/**
 * @file
 * Contains \Drupal\search_api\Service\ServicePluginBase.
 */

namespace Drupal\search_api\Service;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;

/**
 * Defines a base class from which other service classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_service_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the service class.
 * - label: The human-readable name of the service class, translated.
 * - description: A human-readable description for the service class,
 *   translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiService(
 *   id = "my_service",
 *   label = @Translation("My service"),
 *   description = @Translation("Searches with SuperSearch™.")
 * )
 * @endcode
 */
abstract class ServicePluginBase extends ConfigurablePluginBase implements ServiceInterface {

  // @todo: Provide defaults for more methods.

  /**
   * The server this service is configured for.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (!empty($configuration['server']) && $configuration['server'] instanceof ServerInterface) {
      $this->setServer($configuration['server']);
    }
  }

  /**
   * @param \Drupal\search_api\Server\ServerInterface $server
   */
  public function setServer($server) {
    $this->server = $server;
  }

  /**
   * @return \Drupal\search_api\Server\ServerInterface
   */
  public function getServer() {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDatatype($type) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function postInsert() {}

  /**
   * {@inheritdoc}
   */
  public function preUpdate() {}

  /**
   * {@inheritdoc}
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    $this->deleteAllItems();
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function removeIndex(IndexInterface $index) {}

}
