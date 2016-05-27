<?php

namespace Drupal\search_api_test\Plugin\search_api\tracker;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\search_api\tracker\Basic;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a tracker implementation which uses a FIFO-like processing order.
 *
 * @SearchApiTracker(
 *   id = "search_api_test",
 *   label = @Translation("Test tracker"),
 * )
 */
class TestTracker extends Basic {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'foo' => 'test',
      'dependencies' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return array(
      'foo' => array(
        '#type' => 'textfield',
        '#title' => 'Foo',
        '#default_value' => $this->configuration['foo'],
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted(array $ids = NULL) {
    $this->logMethodCall(__FUNCTION__, func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->configuration['dependencies'];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      $this->configuration['dependencies'] = array();
    }
    return $remove;
  }

}
