<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServicePluginBase.
 */

namespace Drupal\search_api\Service;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;

/**
 * Abstract base class for search service plugins.
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
