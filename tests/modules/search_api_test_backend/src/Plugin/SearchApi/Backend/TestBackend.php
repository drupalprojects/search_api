<?php

/**
 * @file
 * Contains \Drupal\search_api_test_backend\Plugin\SearchApi\Backend\TestBackend.
 */

namespace Drupal\search_api_test_backend\Plugin\SearchApi\Backend;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendPluginBase;

/**
 * @SearchApiBackend(
 *   id = "search_api_test_backend",
 *   label = @Translation("Test backend"),
 *   description = @Translation("Dummy backend implementation")
 * )
 */
class TestBackend extends BackendPluginBase {

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
  public function defaultConfiguration() {
    return array('test' => '');
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return array(
      array(
        'label' => 'Dummy Info',
        'info' => 'Dummy Value',
        'status' => 'error',
      ),
      array(
        'label' => 'Dummy Info 2',
        'info' => 'Dummy Value 2',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    return array_keys($items);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $index->reindex();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {}

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems(IndexInterface $index = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $results = array();
    $datasource = key($query->getIndex()->getDatasources());
    if ($query->getKeys() && $query->getKeys()[0] == 'test') {
      $results['results'][$datasource . IndexInterface::DATASOURCE_ID_SEPARATOR . '1'] = array(
        'id' => 1,
        'score' => 2,
        'datasource' => $datasource,
        'excerpt' => 'test',
      );
    }
    elseif ($query->getOption('search_api_mlt')) {
      $results['results'][$datasource . IndexInterface::DATASOURCE_ID_SEPARATOR . '2'] = array(
        'id' => 2,
        'score' => 2,
        'datasource' => $datasource,
        'excerpt' => 'test test',
      );
    }
    else {
      $results['results'][$datasource . IndexInterface::DATASOURCE_ID_SEPARATOR . '1'] = array(
        'id' => 1,
        'score' => 1,
        'datasource' => $datasource,
      );
      $results['results'][$datasource . IndexInterface::DATASOURCE_ID_SEPARATOR . '2'] = array(
        'id' => 2,
        'score' => 1,
        'datasource' => $datasource,
      );
    }
    $results['result count'] = count($results['results']);
    return $results;
  }


  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    if ($feature == 'search_api_mlt') {
      return TRUE;
    }
    return parent::supportsFeature($feature);
  }

}
