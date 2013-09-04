<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\NodeStatus.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Filters out unpublished nodes while indexing.
 *
 * @SearchApiProcessor(
 *   id = "search_api_node_status",
 *   name = @Translation("Exclude unpublished nodes"),
 *   description = @Translation("Exclude unpublished nodes from the index."),
 *   weight = -20
 * )
 */
class NodeStatus extends ProcessorPluginBase {

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * Returns TRUE only for indexes on nodes.
   */
  public static function supportsIndex(IndexInterface $index) {
    return $index->getEntityType() === 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $nid => &$item) {
      if (empty($item->status)) {
        unset($items[$nid]);
      }
    }
  }

}
