<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_add_url_processor",
 *   label = @Translation("Add URL"),
 *   description = @Translation(" Search API data alteration callback that adds an URL field for all items.")
 * )
 */
class AddURL extends FieldsProcessorPluginBase {

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
        'description' => t('An URI where the item can be accessed.'),
        'type' => 'uri',
      ),
    );
  }

}
