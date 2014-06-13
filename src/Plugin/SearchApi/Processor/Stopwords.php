<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Stopwords.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "search_api_stopwords_processor",
 *   label = @Translation("Stop words processor"),
 *   description = @Translation("Words to be filtered out before indexing")
 * )
 */
class Stopwords extends FieldsProcessorPluginBase {

  /**
   * Holds all words ignored for the last query.
   *
   * @var array
   */
  protected $ignored = array();

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'stopwords' => array(
        "a",
        "an",
        "and",
        "are",
        "as",
        "at",
        "be",
        "but",
        "by",
        "for",
        "if",
        "in",
        "into",
        "is",
        "it",
        "no",
        "not",
        "of",
        "on",
        "or",
        "s",
        "such",
        "t",
        "that",
        "the",
        "their",
        "then",
        "there",
        "these",
        "they",
        "this",
        "to",
        "was",
        "will",
        "with",
      ),
      'file' => '',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['help'] = array(
      '#markup' => '<p>' . $this->t('Provide a stopwords file or enter the words in this form. If you do both, both will be used. Read about <a href="!stopwords">stopwords</a>.', array('!stopwords' => 'https://en.wikipedia.org/wiki/Stop_words')) . '</p>'
    );

    $form['stopwords'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Stopwords'),
      '#description' => $this->t('Enter a space and/or linebreak separated list of stopwords that will be removed from content before it is indexed and from search terms before searching.'),
      '#default_value' => implode("\n", $this->configuration['stopwords']),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return Utility::isTextType($type, array('text', 'tokenized_text', 'string'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    parent::submitConfigurationForm($form, $form_state);
    // Convert our text input to an array.
    $this->configuration['stopwords'] = explode("\n", $form_state['values']['stopwords']);
  }

  /**
   * {@inheritdoc}
   */
  public function process(&$value) {
    $stopwords = $this->getStopWords();
    if (empty($stopwords) || !is_string($value)) {
      return;
    }
    $stopwords_preg_replace = implode('|', $stopwords);
    $value = preg_replace('@('. $stopwords_preg_replace .')@siU', '', $value);
    // Remove extra spaces.
    $value = preg_replace('/\s+/s', ' ', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $this->ignored = array();
    parent::preprocessSearchQuery($query);
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    foreach ($this->ignored as $ignored_search_key) {
      $results->addIgnoredSearchKey($ignored_search_key);
    }
  }

  /**
   * Gets all the stopwords.
   *
   * @return array
   *   An array whose keys are the stopwords set in either the file or the text
   *   field.
   */
  protected function getStopWords() {
    if (isset($this->stopwords)) {
      return $this->stopwords;
    }
    $file_words = $form_words = array();
    if (!empty($this->configuration['file']) && $stopwords_file = file_get_contents($this->configuration['file'])) {
      $file_words = preg_split('/\s+/', $stopwords_file);
    }
    if (!empty($this->configuration['stopwords'])) {
      $form_words = $this->configuration['stopwords'];
    }
    $this->stopwords = array_merge($file_words, $form_words);
    return $this->stopwords;
  }
}
