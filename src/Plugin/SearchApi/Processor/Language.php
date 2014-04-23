<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Index\IndexInterface;

/**
 * @SearchApiProcessor(
 *   id = "search_api_language_processor",
 *   label = @Translation("Language Filter"),
 *   description = @Translation("Enables indexing based on language")
 * )
 */
class Language extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'languages' => array(),
    );
  }

  /**
   * Only returns TRUE if the system is multilingual.
   *
   * @see drupal_multilingual()
   */
  public static function supportsIndex(IndexInterface $index) {
    return \Drupal::languageManager()->isMultilingual();
  }
  
  /**
   * Builds configuration form
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    foreach (\Drupal::languageManager()->getLanguages() as $language_id => $language) {
      $language_options[$language_id] = $language->name;
    }

    $form['languages'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Indexed languages'),
      '#description' => t('Index only items in the selected languages. ' .
          'When no languages are selected from the list then there will be no language-related restrictions.'),
      '#options' => $language_options,
      '#default_value' => $this->configuration['languages'],
    );

    return $form;
  }

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
    //if no languages are set then we don't need to do anything
    if (empty($this->configuration['languages'])) {
      return;
    }

    //remove items that have not been explicitly set in our configuration
    foreach ($items as $nid => &$item) {
      if (!isset($this->configuration['languages'][$item->language()->id]) || 
        empty($this->configuration['languages'][$item->language()->id])) {
          unset($items[$nid]);
      }
    }
  }

}
