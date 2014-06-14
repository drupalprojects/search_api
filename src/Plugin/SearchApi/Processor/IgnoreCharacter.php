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
   * @var string
   */
  protected $ignorable;


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
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

    $form['ignorable'] = array(
      '#type' => 'textfield',
      '#title' => t('Ignorable characters'),
      '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). The same format as above is used.'),
      '#default_value' => $this->configuration['ignorable'],
    );

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
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $ignorable = str_replace('/', '\/', $form_state['values']['ignorable']);
    if (@preg_match('/(' . $ignorable . ')+/u', '') === FALSE) {
      $el = $form['ignorable'];
      \Drupal::formBuilder()->setError($el, $form_state, $el['#title'] . ': ' . t('The entered text is no valid regular expression.'));
    }
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
    $this->prepare();
    print_r($this->ignorable);
    if ($this->ignorable) {
      $value = preg_replace('/(' . $this->ignorable . ')+/u', '', $value);
    }

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
    if (!empty($character_sets) && is_array($character_sets)) {
      foreach ($character_sets as $character_set) {
        $regex = $this->getFormatRegularExpression($character_set);
        if (!empty($regex)) {
          $text = preg_replace('/[' . $regex . ']+/u', '', $text);
        }
      }
    }
    print_r($text);
    #$text = preg_replace('/\s(' . $character_set_regex . ')/s', ' ', $text);

    $text = preg_replace('/(¿|¡|!|\?|,|\.|:|;)/s', '', $text);
    $text = trim($text);
    print_r($text);
    return $text;
  }

  /**
   * @param $character_set
   * @return bool|string
   */
  private function getFormatRegularExpression($character_set) {
    switch ($character_set) {
      case 'Pc':
        return '\x{005f}\x{203f}';
\x{2040}
\x{2054}
\x{FE33}
\x{FE34}
\x{FE4D}
\x{FE4E}
\x{FE4F}
\x{FF3F}

\x{002D}
\x{058A}
\x{05BE}
\x{1400}
\x{1806}
\x{2010}
\x{2011}
\x{2012}
\x{2013}
\x{2014}
\x{2015}
\x{2E17}
\x{2E1A}
\x{2E3A}
\x{2E3B}
\x{301C}
\x{3030}
\x{30A0}
\x{FE31}
\x{FE32}
\x{FE58}
\x{FE63}
\x{FF0D}

\x{0029}
\x{005D}
\x{007D}
\x{0F3B}
\x{0F3D}
\x{169C}
\x{2046}
\x{207E}
\x{208E}
\x{2309}
\x{230B}
\x{232A}
\x{2769}
\x{276B}
\x{276D}
\x{276F}
\x{2771}
\x{2773}
\x{2775}
\x{27C6}
\x{27E7}
\x{27E9}
\x{27EB}
\x{27ED}
\x{27EF}
\x{2984}
\x{2986}
\x{2988}
\x{298A}
\x{298C}
\x{298E}
\x{2990}
\x{2992}
\x{2994}
\x{2996}
\x{2998}
\x{29D9}
\x{29DB}
\x{29FD}
\x{2E23}
\x{2E25}
\x{2E27}
\x{2E29}
\x{3009}
\x{300B}
\x{300D}
\x{300F}
\x{3011}
\x{3015}
\x{3017}
\x{3019}
\x{301B}
\x{301E}
\x{301F}
\x{FD3F}
\x{FE18}
\x{FE36}
\x{FE38}
\x{FE3A}
\x{FE3C}
\x{FE3E}
\x{FE40}
\x{FE42}
\x{FE44}
\x{FE48}
\x{FE5A}
\x{FE5C}
\x{FE5E}
\x{FF09}
\x{FF3D}
\x{FF5D}
\x{FF60}
\x{FF63}

\x{00BB}
\x{2019}
\x{201D}
\x{203A}
\x{2E03}
\x{2E05}
\x{2E0A}
\x{2E0D}
\x{2E1D}
\x{2E21}

\x{00AB}
\x{2018}
\x{201B}
\x{201C}
\x{201F}
\x{2039}
\x{2E02}
\x{2E04}
\x{2E09}
\x{2E0C}
\x{2E1C}
\x{2E20}

http://www.fileformat.info/info/unicode/category/Po/list.htm
http://www.fileformat.info/info/unicode/category/Ps/list.htm
http://www.fileformat.info/info/unicode/category/Cc/list.htm
http://www.fileformat.info/info/unicode/category/Cf/list.htm
http://www.fileformat.info/info/unicode/category/Co/list.htm
http://www.fileformat.info/info/unicode/category/Cs/list.htm
http://www.fileformat.info/info/unicode/category/LC/list.htm
http://www.fileformat.info/info/unicode/category/Ll/list.htm
http://www.fileformat.info/info/unicode/category/Ln/list.htm
http://www.fileformat.info/info/unicode/category/Lo/list.htm
http://www.fileformat.info/info/unicode/category/Lt/list.htm
http://www.fileformat.info/info/unicode/category/Lu/list.htm
http://www.fileformat.info/info/unicode/category/Mc/list.htm
http://www.fileformat.info/info/unicode/category/Me/list.htm
http://www.fileformat.info/info/unicode/category/Mn/list.htm
http://www.fileformat.info/info/unicode/category/Nd/list.htm
http://www.fileformat.info/info/unicode/category/Nl/list.htm
http://www.fileformat.info/info/unicode/category/No/list.htm
http://www.fileformat.info/info/unicode/category/Sc/list.htm
http://www.fileformat.info/info/unicode/category/Sk/list.htm
http://www.fileformat.info/info/unicode/category/Sm/list.htm
http://www.fileformat.info/info/unicode/category/So/list.htm
http://www.fileformat.info/info/unicode/category/Zl/list.htm
http://www.fileformat.info/info/unicode/category/Zp/list.htm
http://www.fileformat.info/info/unicode/category/Zs/list.htm

        break;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $this->prepare();
    // We don't touch integers, NULL values or the like.
    if ($this->ignorable) {
      $this->prepare();
      $value = preg_replace('/' . $this->ignorable . '+/u', '', $value);
    }
    if (is_string($value)) {
      if (!empty($this->configuration['strip']['character_sets'])) {
        $value = $this->stripCharacterSets($value);
      }
    }
  }

  /**
   * Prepares the settings.
   */
  protected function prepare() {
    if (!isset($this->ignorable)) {
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
