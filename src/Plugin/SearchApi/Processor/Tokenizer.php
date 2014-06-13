<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "tokenizer",
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
      'strip' => array(
        'character_sets' => array(
          'Pc' => 'Pc',
          'Pd' => 'Pd',
          'Pe' => 'Pe',
          'Pf' => 'Pf',
          'Pi' => 'Pi',
          'Po' => 'Po',
          'Ps' => 'Ps',
          'Do' => 'Do',
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $character_sets = $this->getCharacterSets();
    $form['strip'] = array(
      '#type' => 'details',
      '#title' => t('Character Sets to remove from text'),
      '#description' => t('These character set remove any punctuation characters or any other that you configure. This allows you to send only useful characters to your search index.'),
      '#open' => FALSE,
    );
    // Build the bundle selection element.
    $form['strip']['character_sets'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Strip Character Sets'),
      '#options' => $character_sets,
      '#default_value' => $this->configuration['strip']['character_sets'],
      '#multiple' => TRUE,
    );
    $form['spaces'] = array(
      '#type' => 'textfield',
      '#title' => t('Whitespace characters'),
      '#description' => t('Specify the characters that should be regarded as whitespace and therefore used as word-delimiters. ' .
          'Specify the characters as a <a href="@link">PCRE character class</a>. ' .
          'Note: For non-English content, the default setting might not be suitable.',
          array('@link' => url('http://www.php.net/manual/en/regexp.reference.character-classes.php'))),
      '#default_value' => $this->configuration['spaces'],
    );
    $form['ignorable'] = array(
      '#type' => 'textfield',
      '#title' => t('Ignorable characters'),
      '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). The same format as above is used.'),
      '#default_value' => $this->configuration['ignorable'],
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
    return Utility::isTextType($type, array('text', 'tokenized_text'));
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value) {
    $this->prepare();
    if ($this->ignorable) {
      $value = preg_replace('/(' . $this->ignorable . ')+/u', '', $value);
    }

    if (!empty($this->configuration['strip']['character_sets'])) {
      $value = $this->stripCharacterSets($value);
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
   * Strips unwanted Characters from the value that is currently being
   * processed.
   *
   * @param $text
   * @return string
   */
  protected function stripCharacterSets($text) {
    // Get our configuration
    $character_sets = $this->configuration['strip']['character_sets'];

    $character_set_regex = '';
    // Custom Extra Drupal Characters that we want to remove.
    if (isset($character_sets['Do'])) {
      $character_set_regex .= '!|\?|,|\.|:|;|';
    }

    $character_set_regex .= '\p{' . implode('}|\p{', $character_sets) . '}';
    $text = preg_replace('/\s(' . $character_set_regex . ')/s', '$1', $text);
    $text = preg_replace('/(\p{Ps}|¿|¡)\s/s', '$1', $text);
    $text = trim($text);
    return $text;
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

  /**
   * Prepares the settings.
   */
  protected function prepare() {
    if (!isset($this->spaces)) {
      $this->spaces = str_replace('/', '\/', $this->configuration['spaces']);
      $this->ignorable = str_replace('/', '\/', $this->configuration['ignorable']);
    }
  }

  /**
   * Lists the different UTF8 character sets
   *
   */
  protected function getCharacterSets() {
    return array(
      'Pc' => t("Punctuation, Connector Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pc/list.htm"))),
      'Pd' => t("Punctuation, Dash Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pd/list.htm"))),
      'Pe' => t("Punctuation, Close Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pe/list.htm"))),
      'Pf' => t("Punctuation, Final quote Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pf/list.htm"))),
      'Pi' => t("Punctuation, Initial quote Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pi/list.htm"))),
      'Po' => t("Punctuation, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Po/list.htm"))),
      'Ps' => t("Punctuation, Open Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Ps/list.htm"))),

      'Do' => t("Drupal, Other Normal Punctuation Characters (! ?, . : ; )"),

      'Cc' => t("Other, Control Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Cc/list.htm"))),
      'Cf' => t("Other, Format Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Cf/list.htm"))),
      'Co' => t("Other, Private Use Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Co/list.htm"))),
      'Cs' => t("other, Surrogate Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Cs/list.htm"))),

      'LC' => t("Letter, Cased Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/LC/list.htm"))),
      'Ll' => t("Letter, Lowercase Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Ll/list.htm"))),
      'Lm' => t("Letter, Modifier Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Ln/list.htm"))),
      'Lo' => t("Letter, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Lo/list.htm"))),
      'Lt' => t("Letter, Titlecase Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Lt/list.htm"))),
      'Lu' => t("Letter, Uppercase Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Lu/list.htm"))),

      'Mc' => t("Mark, Spacing Combining Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mc/list.htm"))),
      'Me' => t("Mark, Enclosing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Me/list.htm"))),
      'Mn' => t("Mark, Nonspacing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mn/list.htm"))),

      'Nd' => t("Number, Decimal Digit Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Nd/list.htm"))),
      'Nl' => t("Number, Letter Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Nl/list.htm"))),
      'No' => t("Number, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/No/list.htm"))),

      'Sc' => t("Symbol, Currency Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sc/list.htm"))),
      'Sk' => t("Symbol, Modifier Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sk/list.htm"))),
      'Sm' => t("Symbol, Math Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sm/list.htm"))),
      'So' => t("Symbol, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/So/list.htm"))),

      'Zl' => t("Separator, Line Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zl/list.htm"))),
      'Zp' => t("Separator, Paragraph Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zp/list.htm"))),
      'Zs' => t("Separator, Space Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zs/list.htm"))),
    );
  }

}
