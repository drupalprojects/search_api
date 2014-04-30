<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\Language\Language as CoreLanguage;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_language_processor",
 *   label = @Translation("Language"),
 *   description = @Translation("Adds the item language to indexed items.")
 * )
 */
class Language extends ProcessorPluginBase {

  // @todo Config form for setting the field containing the langcode if
  // language() is not available.

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if ($datasource) {
      return;
    }
    $definition = array(
      'label' => $this->t('Item language'),
      'description' => $this->t('The language code of the item.'),
      'type' => 'string',
    );
    $properties['search_api_language'] = new DataDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $i => $item) {
      $items[$i]['search_api_language']['original_type'] = 'string';
      if ($item['#item'] instanceof TranslatableInterface) {
        $items[$i]['search_api_language']['value'] = array($item['#item']->language()->id);
      }
      else {
        $items[$i]['search_api_language']['value'] = array(CoreLanguage::LANGCODE_NOT_SPECIFIED);
      }
    }
  }

}
