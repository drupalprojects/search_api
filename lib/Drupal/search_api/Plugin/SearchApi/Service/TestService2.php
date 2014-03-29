<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Service\ServicePluginBase;

/**
 * @SearchApiService(
 *   id = "search_api_test_service2",
 *   label = @Translation("Test service 2"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService2 extends ServicePluginBase {

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
  public function indexItems(IndexInterface $index, array $items) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems(IndexInterface $index = NULL) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    return array(
      'result count' => 1,
      'results' => array(
        1 => array(
          'id' => 1,
          'score' => 1,
        ),
        2 => array(
          'id' => 2,
          'score' => 1,
        ),
      ),
    );
  }

}
