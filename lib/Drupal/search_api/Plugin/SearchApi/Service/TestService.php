<?php

namespace Drupal\search_api\Plugin\SearchApi\Service;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Service\ServicePluginBase;

/**
 * @SearchApiService(
 *   id = "search_api_test_service",
 *   label = @Translation("Test service"),
 *   description = @Translation("Dummy service implementation")
 * )
 */
class TestService extends ServicePluginBase {

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
  public function deleteAllItems(IndexInterface $index = NULL) {}

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
