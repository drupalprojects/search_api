<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\Processor;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @Processor(
 *   id = "search_api_test_service",
 *   label = @Translation("Test service"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestProcessor extends ProcessorPluginBase {
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
