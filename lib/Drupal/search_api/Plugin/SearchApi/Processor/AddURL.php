<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_add_url_processor",
 *   label = @Translation("URL field"),
 *   description = @Translation("Adds the item's URL to the indexed data.")
 * )
 */
class AddURL extends ProcessorPluginBase {

  public function preprocessIndexItems(array &$items) {
    foreach ($items as &$item) {
      $url = $this->index->datasource()->getItemUrl($item);
      if (!$url) {
        $item->search_api_url = NULL;
        continue;
      }
      $item->search_api_url = url($url['path'], array('absolute' => TRUE) + $url['options']);
    }
  }

  public function propertyInfo() {
    return array(
      'search_api_url' => array(
        'label' => t('URI'),
        'description' => t('A URI where the item can be accessed.'),
        'type' => 'uri',
      ),
    );
  }

}
