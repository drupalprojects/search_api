<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

/**
 * @SearchApiService(
 *   id = "search_api_test_service2",
 *   label = @Translation("Test service 2"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService2 extends TestService {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array('test' => '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form['test'] = array(
      '#type' => 'textfield',
      '#title' => t('Test'),
      '#default_value' => isset($this->configuration['test']) ? $this->configuration['test'] : '',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $index->reindex();
    return TRUE;
  }

}
