<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "search_api_tokenizer_processor",
 *   label = @Translation("Tokenizer processor"),
 *   description = @Translation("Remove characters from search strings.")
 * )
 */
class Tokenizer extends FieldsProcessorPluginBase {

  /**
   * @var string
   */
  protected $spaces;

  /**
   * @var string
   */
  protected $ignorable;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'spaces' => "[^[:alnum:]]",
      'ignorable' => "[']",
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form += array(
      'spaces' => array(
        '#type' => 'textfield',
        '#title' => t('Whitespace characters'),
        '#description' => t('Specify the characters that should be regarded as whitespace and therefore used as word-delimiters. ' .
            'Specify the characters as a <a href="@link">PCRE character class</a>. ' .
            'Note: For non-English content, the default setting might not be suitable.',
            array('@link' => url('http://www.php.net/manual/en/regexp.reference.character-classes.php'))),
        '#default_value' => $this->configuration['spaces'],
      ),
      'ignorable' => array(
        '#type' => 'textfield',
        '#title' => t('Ignorable characters'),
        '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). The same format as above is used.'),
        '#default_value' => $this->configuration['ignorable'],
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $spaces = str_replace('/', '\/', $form_state['values']['spaces']);
    $ignorable = str_replace('/', '\/', $form_state['values']['ignorable']);
    if (@preg_match('/(' . $spaces . ')+/u', '') === FALSE) {
      $el = $form['spaces'];
      \Drupal::formBuilder()->setError($el, $form_state, $el['#title'] . ': ' . t('The entered text is no valid regular expression.'));
    }
    if (@preg_match('/(' . $ignorable . ')+/u', '') === FALSE) {
      $el = $form['ignorable'];
      \Drupal::formBuilder()->setError($el, $form_state, $el['#title'] . ': ' . t('The entered text is no valid regular expression.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return Utility::isTextType($type, array('text', 'tokens'));
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value) {
    $this->prepare();
    if ($this->ignorable) {
      $value = preg_replace('/(' . $this->ignorable . ')+/u', '', $value);
    }
    if ($this->spaces) {
      $arr = preg_split('/(' . $this->spaces . ')+/u', $value);
      if (count($arr) > 1) {
        $value = array();
        foreach ($arr as $token) {
          $value[] = array('value' => $token);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $this->prepare();
      if ($this->ignorable) {
        $value = preg_replace('/' . $this->ignorable . '+/u', '', $value);
      }
      if ($this->spaces) {
        $value = preg_replace('/' . $this->spaces . '+/u', ' ', $value);
      }
    }
  }

  protected function prepare() {
    if (!isset($this->spaces)) {
      $this->spaces = str_replace('/', '\/', $this->configuration['spaces']);
      $this->ignorable = str_replace('/', '\/', $this->configuration['ignorable']);
    }
  }

}
