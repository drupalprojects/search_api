<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_node_status_processor",
 *   label = @Translation("Node Status"),
 *   description = @Translation("Exclude unpublished nodes from node indexes.")
 * )
 */
class NodeStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $id => &$item) {
      if ($this->index->getDatasource($item['#datasource'])->getEntityTypeId() == 'node' && !$item['#item']->isPublished()) {
        unset($items[$id]);
      }
    }
  }

}
