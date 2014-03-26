<?php

/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorPluginBase.
 */

namespace Drupal\search_api\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\IndexPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * Abstract base class for search processor plugins.
 */
abstract class ProcessorPluginBase extends IndexPluginBase implements ProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   * @param array $properties
   *
   * @return array
   */
  public function alterPropertyDefinitions(array &$properties) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {}

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {}

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query) {}

}
