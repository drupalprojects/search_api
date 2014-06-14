<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "ignoreCharacter",
 *   label = @Translation("Ignore Character processor"),
 *   description = @Translation("Ignore/Remove characters from search strings.")
 * )
 */
class IgnoreCharacter extends FieldsProcessorPluginBase {


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
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
  protected function processFieldValue(&$value, &$type) {
    if (!empty($this->configuration['strip']['character_sets'])) {
      $value = $this->stripCharacterSets($value);
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

    $character_set_regex .= '/\s(\p{' . implode('}|\p{', $character_sets) . '})/s';
    $text = preg_replace($character_set_regex, '$1', $text);
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
      $value = $this->stripCharacterSets($value);
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
