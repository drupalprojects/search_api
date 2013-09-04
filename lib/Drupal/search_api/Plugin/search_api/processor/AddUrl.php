<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\AddUrl.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Search API data alteration callback that adds an URL field for all items.
 *
 * @SearchApiProcessor(
 *   id = "search_api_add_url",
 *   name = @Translation("URL field"),
 *   description = @Translation("Adds the item's URL to the indexed data."),
 *   weight = -10
 * )
 */
class AddUrl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return array(
      'search_api_url' => array(
        'label' => t('URI'),
        'description' => t('An URI where the item can be accessed.'),
        'type' => 'uri',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $id => &$item) {
      $url = $this->index->datasource()->getItemUrl($item);
      if (!$url) {
        $item->search_api_url = NULL;
        continue;
      }
      $item->search_api_url = url($url['path'], array('absolute' => TRUE) + $url['options']);
    }
  }

}
