<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\Service;
use Drupal\search_api\Service\ServicePluginBase;

/**
 * @Service(
 *   id = "search_api_test_service",
 *   name = @Translation("Test service"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService extends ServicePluginBase {
  public function buildConfigurationForm(array $form, array &$form_state) {

  }
  public function defaultConfiguration() {

  }
  public function getConfiguration() {

  }
  public function setConfiguration(array $configuration) {

  }
  public function submitConfigurationForm(array &$form, array &$form_state) {

  }
  public function validateConfigurationForm(array &$form, array &$form_state) {

  }
}
