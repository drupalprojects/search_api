<?php

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
    // @todo Re-introduce Datasource::getEntityType() for this?
    foreach ($index->getDatasources() as $datasource) {
      $definition = $datasource->getPluginDefinition();
      if (isset($definition['entity_type']) && $definition['entity_type'] === 'node') {
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
      // @todo Only process nodes (check '#datasource' key).
      if (!$item['#item']->isPublished()) {
        unset($items[$id]);
      }
    }
  }

}
