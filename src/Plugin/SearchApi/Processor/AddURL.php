<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\AddURL.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_add_url_processor",
 *   label = @Translation("URL field"),
 *   description = @Translation("Adds the item's URL to the indexed data.")
 * )
 */
class AddURL extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as &$item) {
      // Only run if the field is enabled for the index.
      if (!empty($item[$item['#datasource'] . '|search_api_url'])) {
        /* @param $url \Drupal\Core\Url */
        $url = $this->index->getDatasource($item['#datasource'])->getItemUrl($item['#item']);
        if ($url) {
          $item[$item['#datasource'] . '|search_api_url']['value'][] = $url->toString();
          $item[$item['#datasource'] . '|search_api_url']['original_type'] = 'uri';
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if ($datasource) {
      return;
    }
    $definition = array(
      'label' => $this->t('URI'),
      'description' => $this->t('A URI where the item can be accessed.'),
      'type' => 'uri',
    );
    $properties['search_api_url'] = new DataDefinition($definition);
  }

}
