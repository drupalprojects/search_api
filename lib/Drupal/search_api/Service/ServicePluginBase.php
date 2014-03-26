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
