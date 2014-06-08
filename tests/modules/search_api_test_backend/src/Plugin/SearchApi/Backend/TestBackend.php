<?php

/**
 * @file
 * Contains \Drupal\search_api_test_backend\Plugin\SearchApi\Backend\TestBackend.
 */

namespace Drupal\search_api_test_backend\Plugin\SearchApi\Backend;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\Utility\Utility;

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
    $results = Utility::createSearchResultSet($query);
    $result_items = array();
    $datasources = $query->getIndex()->getDatasources();
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    $datasource = reset($datasources);
    if ($query->getKeys() && $query->getKeys()[0] == 'test') {
      $item_id = Utility::createCombinedId($datasource->getPluginId(), '1');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(2);
      $item->setExcerpt('test');
      $result_items[$item_id] = $item;
    }
    elseif ($query->getOption('search_api_mlt')) {
      $item_id = Utility::createCombinedId($datasource->getPluginId(), '2');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(2);
      $item->setExcerpt('test test');
      $result_items[$item_id] = $item;
    }
    else {
      $item_id = Utility::createCombinedId($datasource->getPluginId(), '1');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(1);
      $result_items[$item_id] = $item;
      $item_id = Utility::createCombinedId($datasource->getPluginId(), '2');
      $item = Utility::createItem($query->getIndex(), $item_id, $datasource);
      $item->setScore(1);
      $result_items[$item_id] = $item;
    }
    $results->setResultCount(count($result_items));
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
