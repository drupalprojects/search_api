<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

use Drupal\search_api\Service\ServicePluginBase;

/**
 * @SearchApiService(
 *   id = "search_api_test_service",
 *   label = @Translation("Test service"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService extends ServicePluginBase {
  protected $configuration = array();

  public function buildConfigurationForm(array $form, array &$form_state) {

  }
  public function defaultConfiguration() {
    return array();
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
  public function addIndex(\Drupal\search_api\Index\IndexInterface $index) {

  }
  public function deleteAllItems(\Drupal\search_api\Index\IndexInterface $index) {

  }
  public function deleteItems(\Drupal\search_api\Index\IndexInterface $index, array $ids) {

  }
  public function hasIndex(\Drupal\search_api\Index\IndexInterface $index) {

  }
  public function indexItems(\Drupal\search_api\Index\IndexInterface $index, array $items) {

  }
  public function postInstanceConfigurationCreate() {

  }
  public function postInstanceConfigurationDelete() {

  }
  public function postInstanceConfigurationUpdate() {

  }
  public function preInstanceConfigurationCreate() {

  }
  public function preInstanceConfigurationDelete() {

  }
  public function preInstanceConfigurationUpdate() {

  }
  public function removeIndex(\Drupal\search_api\Index\IndexInterface $index) {

  }
  public function supportsFeature($feature) {

  }
  public function updateIndex(\Drupal\search_api\Index\IndexInterface $index) {

  }
}
