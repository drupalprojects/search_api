<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_node_status_processor",
 *   label = @Translation("Node Status"),
 *   description = @Translation("Exclude unpublished nodes from node indexes.")
 * )
 */
class NodeStatus extends FieldsProcessorPluginBase {

  /**
   * Alter items before indexing.
   *
   * Items which are removed from the array won't be indexed, but will be marked
   * as clean for future indexing.
   *
   * @param array $items
   *   An array of items to be altered, keyed by item IDs.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $nid => &$item) {
      if (empty($item->status)) {
        unset($items[$nid]);
      }
    }
  }

}
