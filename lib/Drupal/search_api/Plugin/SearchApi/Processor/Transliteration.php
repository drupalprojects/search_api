<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use \Drupal;

/**
 * @SearchApiProcessor(
 *   id = "search_api_transliteration_processor",
 *   label = @Translation("Transliteration processor"),
 *   description = @Translation("Processor for making searches insensitive to accents and other non-ASCII characters.")
 * )
 */
class Transliteration extends FieldsProcessorPluginBase {

  /**
  * @var object
  */
  protected $transliterator = NULL;

  /**
  * @var string
  */
  protected $langcode = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    /* Store the language code and transliteration class in this
     * to save recreating it for each 
     */
    $this->langcode = Drupal::languageManager()->getDefaultLanguage()->id;

    $this->transliterator = Drupal::service('transliteration');
  }

  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      if ($this->langcode && $this->transliterator) {
        $value = $this->transliterator->transliterate($value, $this->langcode);
      }
      else {
        //@todo - what should our fallback position be here?
      }
    }
  }

}
