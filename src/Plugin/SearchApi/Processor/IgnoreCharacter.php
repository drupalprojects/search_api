<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cf;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Co;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Mc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Me;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Mn;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pd;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pe;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pf;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pi;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Po;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Ps;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sk;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sm;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\So;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zl;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zp;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zs;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "ignore_character",
 *   label = @Translation("Ignore characters"),
 *   description = @Translation("Configure types of characters which should be ignored for searches.")
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
      'ignorable' => "['¿¡!?,.:;]",
      'strip' => array(
        'character_sets' => array(
          'Pc' => 'Pc',
          'Pd' => 'Pd',
          'Pe' => 'Pe',
          'Pf' => 'Pf',
          'Pi' => 'Pi',
          'Po' => 'Po',
          'Ps' => 'Ps',
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['ignorable'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Ignorable characters'),
      '#description' => $this->t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). It is placed in a regular expression function as such: preg_replace(\[\'¿¡!?,.:;]+/u)'),
      '#default_value' => $this->configuration['ignorable'],
    );

    $character_sets = $this->getCharacterSets();
    $form['strip'] = array(
      '#type' => 'details',
      '#title' => $this->t('Character Sets to remove from text'),
      '#description' => $this->t('These character set remove any punctuation characters or any other that you configure. This allows you to send only useful characters to your search index.'),
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $ignorable = str_replace('/', '\/', $form_state['values']['ignorable']);
    if (@preg_match('/(' . $ignorable . ')+/u', '') === FALSE) {
      $el = $form['ignorable'];
      \Drupal::formBuilder()->setError($el, $form_state, $el['#title'] . ': ' . $this->t('The entered text is no valid regular expression.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return Utility::isTextType($type, array('text', 'tokenized_text', 'string'));
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

    // Loop over the character sets and strip the characters from the text
    if (!empty($character_sets) && is_array($character_sets)) {
      foreach ($character_sets as $character_set) {
        $regex = $this->getFormatRegularExpression($character_set);
        if (!empty($regex)) {
          $text = preg_replace('/[' . $regex . ']+/u', '', $text);
        }
      }
    }
    $text = trim($text);
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $this->prepare();
    // We don't touch integers, NULL values or the like.
    if ($this->ignorable) {
      $value = preg_replace('/' . $this->ignorable . '+/u', '', $value);
    }

    if (!empty($this->configuration['strip']['character_sets'])) {
      $value = $this->stripCharacterSets($value);
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
   * @param $character_set
   * @return bool|string
   */
  private function getFormatRegularExpression($character_set) {
    switch ($character_set) {
      case 'Pc':
        return Pc::getRegularExpression();
      case 'Pd':
        return Pd::getRegularExpression();
      case 'Pe':
        return Pe::getRegularExpression();
      case 'Pf':
        return Pf::getRegularExpression();
      case 'Pi':
        return Pi::getRegularExpression();
      case 'Po':
        return Po::getRegularExpression();
      case 'Ps':
        return Ps::getRegularExpression();
      case 'Cc':
        return Cc::getRegularExpression();
      case 'Cf':
        return Cf::getRegularExpression();
      case 'Co':
        return Co::getRegularExpression();
      case 'Mc':
        return Mc::getRegularExpression();
      case 'Me':
        return Me::getRegularExpression();
      case 'Mn':
        return Mn::getRegularExpression();
      case 'Sc':
        return Sc::getRegularExpression();
      case 'Sk':
        return Sk::getRegularExpression();
      case 'Sm':
        return Sm::getRegularExpression();
      case 'So':
        return So::getRegularExpression();
      case 'Zl':
        return Zl::getRegularExpression();
      case 'Zp':
        return Zp::getRegularExpression();
      case 'Zs':
        return Zs::getRegularExpression();
    }
    return FALSE;
  }

  /**
   * Lists the different UTF8 character sets
   *
   */
  protected function getCharacterSets() {
    return array(
      'Pc' => $this->t("Punctuation, Connector Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pc/list.htm"))),
      'Pd' => $this->t("Punctuation, Dash Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pd/list.htm"))),
      'Pe' => $this->t("Punctuation, Close Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pe/list.htm"))),
      'Pf' => $this->t("Punctuation, Final quote Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pf/list.htm"))),
      'Pi' => $this->t("Punctuation, Initial quote Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Pi/list.htm"))),
      'Po' => $this->t("Punctuation, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Po/list.htm"))),
      'Ps' => $this->t("Punctuation, Open Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Ps/list.htm"))),
      'Cc' => $this->t("Other, Control Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Cc/list.htm"))),
      'Cf' => $this->t("Other, Format Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Cf/list.htm"))),
      'Co' => $this->t("Other, Private Use Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Co/list.htm"))),

      'Mc' => $this->t("Mark, Spacing Combining Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mc/list.htm"))),
      'Me' => $this->t("Mark, Enclosing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Me/list.htm"))),
      'Mn' => $this->t("Mark, Nonspacing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mn/list.htm"))),

      'Sc' => $this->t("Symbol, Currency Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sc/list.htm"))),
      'Sk' => $this->t("Symbol, Modifier Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sk/list.htm"))),
      'Sm' => $this->t("Symbol, Math Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Sm/list.htm"))),
      'So' => $this->t("Symbol, Other Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/So/list.htm"))),

      'Zl' => $this->t("Separator, Line Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zl/list.htm"))),
      'Zp' => $this->t("Separator, Paragraph Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zp/list.htm"))),
      'Zs' => $this->t("Separator, Space Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Zs/list.htm"))),
    );
  }

}
