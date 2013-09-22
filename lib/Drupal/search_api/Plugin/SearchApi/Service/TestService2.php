<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\Service;
use Drupal\search_api\Service\ServicePluginBase;

/**
 * @Service(
 *   id = "search_api_test_service2",
 *   label = @Translation("Test service 2"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService2 extends ServicePluginBase {
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form['test'] = array(
      '#type' => 'textfield',
      '#title' => t('Test'),
      '#default_value' => isset($this->configuration['test']) ? $this->configuration['test'] : '',
    );
    return $form;
  }
  public function defaultConfiguration() {
    return array('test' => '');
  }
  public function getConfiguration() {
    return $this->configuration;
  }
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }
  public function submitConfigurationForm(array &$form, array &$form_state) {

  }
  public function validateConfigurationForm(array &$form, array &$form_state) {

  }
}
