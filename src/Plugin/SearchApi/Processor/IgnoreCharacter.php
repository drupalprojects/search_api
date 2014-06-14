<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pd;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pe;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pf;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pi;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Po;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Ps;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cf;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Co;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Cs;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Mc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Me;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Mn;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sc;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sk;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Sm;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\So;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zl;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zp;
use Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zs;

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
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['ignorable'] = array(
      '#type' => 'textfield',
      '#title' => t('Ignorable characters'),
      '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). It is placed in a regular expression function as such: preg_replace(\[\'¿¡!?,.:;]+/u)'),
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
    // Remove the characters we do not want
    if ($this->ignorable) {
      $value = preg_replace('/(' . $this->ignorable . ')+/u', '', $value);
    }

    // Strip the character sets
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
   * @param $character_set
   * @return bool|string
   */
  private function getFormatRegularExpression($character_set) {
    if (class_exists($character_set)) {
      $character_class = new $character_set();
      /** @var \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Unicode $character_class */
      $character_class->getCharacters();
    }
    switch ($character_set) {
      case 'Pc':
        $pc = new Pc();
        return $pc->getCharacters();

'\x{2040}\x{2054}\x{FE33}\x{FE34}\x{FE4D}\x{FE4E}\x{FE4F}' .
'\x{FF3F}'






'\x{002D}\x{058A}\x{05BE}\x{1400}\x{1806}\x{2010}\x{2011}' .
'\x{2012}\x{2013}\x{2014}\x{2015}\x{2E17}\x{2E1A}\x{2E3A}' .
'\x{2E3B}\x{301C}\x{3030}\x{30A0}\x{FE31}\x{FE32}\x{FE58}' .
'\x{FE63}\x{FF0D}' .





'\x{0029}\x{005D}\x{007D}\x{0F3B}\x{0F3D}\x{169C}\x{2046}' .
'\x{207E}\x{208E}\x{2309}\x{230B}\x{232A}\x{2769}\x{276B}' .
'\x{276D}\x{276F}\x{2771}\x{2773}\x{2775}\x{27C6}\x{27E7}' .
'\x{27E9}\x{27EB}\x{27ED}\x{27EF}\x{2984}\x{2986}\x{2988}' .
'\x{298A}\x{298C}\x{298E}\x{2990}\x{2992}\x{2994}\x{2996}' .
'\x{2998}\x{29D9}\x{29DB}\x{29FD}\x{2E23}\x{2E25}\x{2E27}' .
'\x{2E29}\x{3009}\x{300B}\x{300D}\x{300F}\x{3011}\x{3015}' .
'\x{3017}\x{3019}\x{301B}\x{301E}\x{301F}\x{FD3F}\x{FE18}' .
'\x{FE36}\x{FE38}\x{FE3A}\x{FE3C}\x{FE3E}\x{FE40}\x{FE42}' .
'\x{FE44}\x{FE48}\x{FE5A}\x{FE5C}\x{FE5E}\x{FF09}\x{FF3D}' .
'\x{FF5D}\x{FF60}\x{FF63}' .






'\x{00BB}\x{2019}\x{201D}\x{203A}\x{2E03}\x{2E05}\x{2E0A}' .
'\x{2E0D}\x{2E1D}\x{2E21}' .






'\x{00AB}\x{2018}\x{201B}\x{201C}\x{201F}\x{2039}\x{2E02}' .
'\x{2E04}\x{2E09}\x{2E0C}\x{2E1C}\x{2E20}' .








'\x{0021}\x{0022}\x{0023}\x{0025}\x{0026}\x{0027}\x{002A}' .
'\x{002C}\x{002E}\x{002F}\x{003A}\x{003B}\x{003F}\x{0040}' .
'\x{005C}\x{00A1}\x{00A7}\x{00B6}\x{00B7}\x{00BF}\x{037E}' .
'\x{0387}\x{055A}\x{055B}\x{055C}\x{055D}\x{055E}\x{055F}' .
'\x{0589}\x{05C0}\x{05C3}\x{05C6}\x{05F3}\x{05F4}\x{0609}' .
'\x{060A}\x{060C}\x{060D}\x{061B}\x{061E}\x{061F}\x{066A}' .
'\x{066B}\x{066C}\x{066D}\x{06D4}\x{0700}\x{0701}\x{0702}' .
'\x{0703}\x{0704}\x{0705}\x{0706}\x{0707}\x{0708}\x{0709}' .
'\x{070A}\x{070B}\x{070C}\x{070D}\x{07F7}\x{07F8}\x{07F9}' .
'\x{0830}\x{0831}\x{0832}\x{0833}\x{0834}\x{0835}\x{0836}' .
'\x{0837}\x{0838}\x{0839}\x{083A}\x{083B}\x{083C}\x{083D}' .
'\x{083E}\x{085E}\x{0964}\x{0965}\x{0970}\x{0AF0}\x{0DF4}' .
'\x{0E4F}\x{0E5A}\x{0E5B}\x{0F04}\x{0F05}\x{0F06}\x{0F07}' .
'\x{0F08}\x{0F09}\x{0F0A}\x{0F0B}\x{0F0C}\x{0F0D}\x{0F0E}' .
'\x{0F0F}\x{0F10}\x{0F11}\x{0F12}\x{0F14}\x{0F85}\x{0FD0}' .
'\x{0FD1}\x{0FD2}\x{0FD3}\x{0FD4}\x{0FD9}\x{0FDA}\x{104A}' .
'\x{104B}\x{104C}\x{104D}\x{104E}\x{104F}\x{10FB}\x{1360}' .
'\x{1361}\x{1362}\x{1363}\x{1364}\x{1365}\x{1366}\x{1367}' .
'\x{1368}\x{166D}\x{166E}\x{16EB}\x{16EC}\x{16ED}\x{1735}' .
'\x{1736}\x{17D4}\x{17D5}\x{17D6}\x{17D8}\x{17D9}\x{17DA}' .
'\x{1800}\x{1801}\x{1802}\x{1803}\x{1804}\x{1805}\x{1807}' .
'\x{1808}\x{1809}\x{180A}\x{1944}\x{1945}\x{1A1E}\x{1A1F}' .
'\x{1AA0}\x{1AA1}\x{1AA2}\x{1AA3}\x{1AA4}\x{1AA5}\x{1AA6}' .
'\x{1AA8}\x{1AA9}\x{1AAA}\x{1AAB}\x{1AAC}\x{1AAD}\x{1B5A}' .
'\x{1B5B}\x{1B5C}\x{1B5D}\x{1B5E}\x{1B5F}\x{1B60}\x{1BFC}' .
'\x{1BFD}\x{1BFE}\x{1BFF}\x{1C3B}\x{1C3C}\x{1C3D}\x{1C3E}' .
'\x{1C3F}\x{1C7E}\x{1C7F}\x{1CC0}\x{1CC1}\x{1CC2}\x{1CC3}' .
'\x{1CC4}\x{1CC5}\x{1CC6}\x{1CC7}\x{1CD3}\x{2016}\x{2017}' .
'\x{2020}\x{2021}\x{2022}\x{2023}\x{2024}\x{2025}\x{2026}' .
'\x{2027}\x{2030}\x{2031}\x{2032}\x{2033}\x{2034}\x{2035}' .
'\x{2036}\x{2037}\x{2038}\x{203B}\x{203C}\x{203D}\x{203E}' .
'\x{2041}\x{2042}\x{2043}\x{2047}\x{2048}\x{2049}\x{204A}' .
'\x{204B}\x{204C}\x{204D}\x{204E}\x{204F}\x{2050}\x{2051}' .
'\x{2053}\x{2055}\x{2056}\x{2057}\x{2058}\x{2059}\x{205A}' .
'\x{205B}\x{205C}\x{205D}\x{205E}\x{2CF9}\x{2CFA}\x{2CFB}' .
'\x{2CFC}\x{2CFE}\x{2CFF}\x{2D70}\x{2E00}\x{2E01}\x{2E06}' .
'\x{2E07}\x{2E08}\x{2E0B}\x{2E0E}\x{2E0F}\x{2E10}\x{2E11}' .
'\x{2E12}\x{2E13}\x{2E14}\x{2E15}\x{2E16}\x{2E18}\x{2E19}' .
'\x{2E1B}\x{2E1E}\x{2E1F}\x{2E2A}\x{2E2B}\x{2E2C}\x{2E2D}' .
'\x{2E2E}\x{2E30}\x{2E31}\x{2E32}\x{2E33}\x{2E34}\x{2E35}' .
'\x{2E36}\x{2E37}\x{2E38}\x{2E39}\x{3001}\x{3002}\x{3003}' .
'\x{303D}\x{30FB}\x{A4FE}\x{A4FF}\x{A60D}\x{A60E}\x{A60F}' .
'\x{A673}\x{A67E}\x{A6F2}\x{A6F3}\x{A6F4}\x{A6F5}\x{A6F6}' .
'\x{A6F7}\x{A874}\x{A875}\x{A876}\x{A877}\x{A8CE}\x{A8CF}' .
'\x{A8F8}\x{A8F9}\x{A8FA}\x{A92E}\x{A92F}\x{A95F}\x{A9C1}' .
'\x{A9C2}\x{A9C3}\x{A9C4}\x{A9C5}\x{A9C6}\x{A9C7}\x{A9C8}' .
'\x{A9C9}\x{A9CA}\x{A9CB}\x{A9CC}\x{A9CD}\x{A9DE}\x{A9DF}' .
'\x{AA5C}\x{AA5D}\x{AA5E}\x{AA5F}\x{AADE}\x{AADF}\x{AAF0}' .
'\x{AAF1}\x{ABEB}\x{FE10}\x{FE11}\x{FE12}\x{FE13}\x{FE14}' .
'\x{FE15}\x{FE16}\x{FE19}\x{FE30}\x{FE45}\x{FE46}\x{FE49}' .
'\x{FE4A}\x{FE4B}\x{FE4C}\x{FE50}\x{FE51}\x{FE52}\x{FE54}' .
'\x{FE55}\x{FE56}\x{FE57}\x{FE5F}\x{FE60}\x{FE61}\x{FE68}' .
'\x{FE6A}\x{FE6B}\x{FF01}\x{FF02}\x{FF03}\x{FF05}\x{FF06}' .
'\x{FF07}\x{FF0A}\x{FF0C}\x{FF0E}\x{FF0F}\x{FF1A}\x{FF1B}' .
'\x{FF1F}\x{FF20}\x{FF3C}\x{FF61}\x{FF64}\x{FF65}\x{10100}' .
'\x{10101}\x{10102}\x{1039F}\x{103D0}\x{10857}\x{1091F}\x{1093F}' .
'\x{10A50}\x{10A51}\x{10A52}\x{10A53}\x{10A54}\x{10A55}\x{10A56}' .
'\x{10A57}\x{10A58}\x{10A7F}\x{10B39}\x{10B3A}\x{10B3B}\x{10B3C}' .
'\x{10B3D}\x{10B3E}\x{10B3F}\x{11047}\x{11048}\x{11049}\x{1104A}' .
'\x{1104B}\x{1104C}\x{1104D}\x{110BB}\x{110BC}\x{110BE}\x{110BF}' .
'\x{110C0}\x{110C1}\x{11140}\x{11141}\x{11142}\x{11143}\x{111C5}' .
'\x{111C6}\x{111C7}\x{111C8}\x{12470}\x{12471}\x{12472}\x{12473}' .





















'\x{0028}\x{005B}\x{007B}\x{0F3A}\x{0F3C}\x{169B}\x{201A}' .
'\x{201E}\x{2045}\x{207D}\x{208D}\x{2308}\x{230A}\x{2329}' .
'\x{2768}\x{276A}\x{276C}\x{276E}\x{2770}\x{2772}\x{2774}' .
'\x{27C5}\x{27E6}\x{27E8}\x{27EA}\x{27EC}\x{27EE}\x{2983}' .
'\x{2985}\x{2987}\x{2989}\x{298B}\x{298D}\x{298F}\x{2991}' .
'\x{2993}\x{2995}\x{2997}\x{29D8}\x{29DA}\x{29FC}\x{2E22}' .
'\x{2E24}\x{2E26}\x{2E28}\x{3008}\x{300A}\x{300C}\x{300E}' .
'\x{3010}\x{3014}\x{3016}\x{3018}\x{301A}\x{301D}\x{FD3E}' .
'\x{FE17}\x{FE35}\x{FE37}\x{FE39}\x{FE3B}\x{FE3D}\x{FE3F}' .
'\x{FE41}\x{FE43}\x{FE47}\x{FE59}\x{FE5B}\x{FE5D}\x{FF08}' .
'\x{FF3B}\x{FF5B}\x{FF5F}\x{FF62}' .


















'\x{0021}\x{0022}\x{0023}\x{0025}\x{0026}\x{0027}\x{002A}' .
'\x{002C}\x{002E}\x{002F}\x{003A}\x{003B}\x{003F}\x{0040}' .
'\x{005C}\x{00A1}\x{00A7}\x{00B6}\x{00B7}\x{00BF}\x{037E}' .
'\x{0387}\x{055A}\x{055B}\x{055C}\x{055D}\x{055E}\x{055F}' .
'\x{0589}\x{05C0}\x{05C3}\x{05C6}\x{05F3}\x{05F4}\x{0609}' .
'\x{060A}\x{060C}\x{060D}\x{061B}\x{061E}\x{061F}\x{066A}' .
'\x{066B}\x{066C}\x{066D}\x{06D4}\x{0700}\x{0701}\x{0702}' .
'\x{0703}\x{0704}\x{0705}\x{0706}\x{0707}\x{0708}\x{0709}' .
'\x{070A}\x{070B}\x{070C}\x{070D}\x{07F7}\x{07F8}\x{07F9}' .
'\x{0830}\x{0831}\x{0832}\x{0833}\x{0834}\x{0835}\x{0836}' .
'\x{0837}\x{0838}\x{0839}\x{083A}\x{083B}\x{083C}\x{083D}' .
'\x{083E}\x{085E}\x{0964}\x{0965}\x{0970}\x{0AF0}\x{0DF4}' .
'\x{0E4F}\x{0E5A}\x{0E5B}\x{0F04}\x{0F05}\x{0F06}\x{0F07}' .
'\x{0F08}\x{0F09}\x{0F0A}\x{0F0B}\x{0F0C}\x{0F0D}\x{0F0E}' .
'\x{0F0F}\x{0F10}\x{0F11}\x{0F12}\x{0F14}\x{0F85}\x{0FD0}' .
'\x{0FD1}\x{0FD2}\x{0FD3}\x{0FD4}\x{0FD9}\x{0FDA}\x{104A}' .
'\x{104B}\x{104C}\x{104D}\x{104E}\x{104F}\x{10FB}\x{1360}' .
'\x{1361}\x{1362}\x{1363}\x{1364}\x{1365}\x{1366}\x{1367}' .
'\x{1368}\x{166D}\x{166E}\x{16EB}\x{16EC}\x{16ED}\x{1735}' .
'\x{1736}\x{17D4}\x{17D5}\x{17D6}\x{17D8}\x{17D9}\x{17DA}' .
'\x{1800}\x{1801}\x{1802}\x{1803}\x{1804}\x{1805}\x{1807}' .
'\x{1808}\x{1809}\x{180A}\x{1944}\x{1945}\x{1A1E}\x{1A1F}' .
'\x{1AA0}\x{1AA1}\x{1AA2}\x{1AA3}\x{1AA4}\x{1AA5}\x{1AA6}' .
'\x{1AA8}\x{1AA9}\x{1AAA}\x{1AAB}\x{1AAC}\x{1AAD}\x{1B5A}' .
'\x{1B5B}\x{1B5C}\x{1B5D}\x{1B5E}\x{1B5F}\x{1B60}\x{1BFC}' .
'\x{1BFD}\x{1BFE}\x{1BFF}\x{1C3B}\x{1C3C}\x{1C3D}\x{1C3E}' .
'\x{1C3F}\x{1C7E}\x{1C7F}\x{1CC0}\x{1CC1}\x{1CC2}\x{1CC3}' .
'\x{1CC4}\x{1CC5}\x{1CC6}\x{1CC7}\x{1CD3}\x{2016}\x{2017}' .
'\x{2020}\x{2021}\x{2022}\x{2023}\x{2024}\x{2025}\x{2026}' .
'\x{2027}\x{2030}\x{2031}\x{2032}\x{2033}\x{2034}\x{2035}' .
'\x{2036}\x{2037}\x{2038}\x{203B}\x{203C}\x{203D}\x{203E}' .
'\x{2041}\x{2042}\x{2043}\x{2047}\x{2048}\x{2049}\x{204A}' .
'\x{204B}\x{204C}\x{204D}\x{204E}\x{204F}\x{2050}\x{2051}' .
'\x{2053}\x{2055}\x{2056}\x{2057}\x{2058}\x{2059}\x{205A}' .
'\x{205B}\x{205C}\x{205D}\x{205E}\x{2CF9}\x{2CFA}\x{2CFB}' .
'\x{2CFC}\x{2CFE}\x{2CFF}\x{2D70}\x{2E00}\x{2E01}\x{2E06}' .
'\x{2E07}\x{2E08}\x{2E0B}\x{2E0E}\x{2E0F}\x{2E10}\x{2E11}' .
'\x{2E12}\x{2E13}\x{2E14}\x{2E15}\x{2E16}\x{2E18}\x{2E19}' .
'\x{2E1B}\x{2E1E}\x{2E1F}\x{2E2A}\x{2E2B}\x{2E2C}\x{2E2D}' .
'\x{2E2E}\x{2E30}\x{2E31}\x{2E32}\x{2E33}\x{2E34}\x{2E35}' .
'\x{2E36}\x{2E37}\x{2E38}\x{2E39}\x{3001}\x{3002}\x{3003}' .
'\x{303D}\x{30FB}\x{A4FE}\x{A4FF}\x{A60D}\x{A60E}\x{A60F}' .
'\x{A673}\x{A67E}\x{A6F2}\x{A6F3}\x{A6F4}\x{A6F5}\x{A6F6}' .
'\x{A6F7}\x{A874}\x{A875}\x{A876}\x{A877}\x{A8CE}\x{A8CF}' .
'\x{A8F8}\x{A8F9}\x{A8FA}\x{A92E}\x{A92F}\x{A95F}\x{A9C1}' .
'\x{A9C2}\x{A9C3}\x{A9C4}\x{A9C5}\x{A9C6}\x{A9C7}\x{A9C8}' .
'\x{A9C9}\x{A9CA}\x{A9CB}\x{A9CC}\x{A9CD}\x{A9DE}\x{A9DF}' .
'\x{AA5C}\x{AA5D}\x{AA5E}\x{AA5F}\x{AADE}\x{AADF}\x{AAF0}' .
'\x{AAF1}\x{ABEB}\x{FE10}\x{FE11}\x{FE12}\x{FE13}\x{FE14}' .
'\x{FE15}\x{FE16}\x{FE19}\x{FE30}\x{FE45}\x{FE46}\x{FE49}' .
'\x{FE4A}\x{FE4B}\x{FE4C}\x{FE50}\x{FE51}\x{FE52}\x{FE54}' .
'\x{FE55}\x{FE56}\x{FE57}\x{FE5F}\x{FE60}\x{FE61}\x{FE68}' .
'\x{FE6A}\x{FE6B}\x{FF01}\x{FF02}\x{FF03}\x{FF05}\x{FF06}' .
'\x{FF07}\x{FF0A}\x{FF0C}\x{FF0E}\x{FF0F}\x{FF1A}\x{FF1B}' .
'\x{FF1F}\x{FF20}\x{FF3C}\x{FF61}\x{FF64}\x{FF65}\x{10100}' .
'\x{10101}\x{10102}\x{1039F}\x{103D0}\x{10857}\x{1091F}\x{1093F}' .
'\x{10A50}\x{10A51}\x{10A52}\x{10A53}\x{10A54}\x{10A55}\x{10A56}' .
'\x{10A57}\x{10A58}\x{10A7F}\x{10B39}\x{10B3A}\x{10B3B}\x{10B3C}' .
'\x{10B3D}\x{10B3E}\x{10B3F}\x{11047}\x{11048}\x{11049}\x{1104A}' .
'\x{1104B}\x{1104C}\x{1104D}\x{110BB}\x{110BC}\x{110BE}\x{110BF}' .
'\x{110C0}\x{110C1}\x{11140}\x{11141}\x{11142}\x{11143}\x{111C5}' .
'\x{111C6}\x{111C7}\x{111C8}\x{12470}\x{12471}\x{12472}\x{12473}' .





















'\x{0028}\x{005B}\x{007B}\x{0F3A}\x{0F3C}\x{169B}\x{201A}' .
'\x{201E}\x{2045}\x{207D}\x{208D}\x{2308}\x{230A}\x{2329}' .
'\x{2768}\x{276A}\x{276C}\x{276E}\x{2770}\x{2772}\x{2774}' .
'\x{27C5}\x{27E6}\x{27E8}\x{27EA}\x{27EC}\x{27EE}\x{2983}' .
'\x{2985}\x{2987}\x{2989}\x{298B}\x{298D}\x{298F}\x{2991}' .
'\x{2993}\x{2995}\x{2997}\x{29D8}\x{29DA}\x{29FC}\x{2E22}' .
'\x{2E24}\x{2E26}\x{2E28}\x{3008}\x{300A}\x{300C}\x{300E}' .
'\x{3010}\x{3014}\x{3016}\x{3018}\x{301A}\x{301D}\x{FD3E}' .
'\x{FE17}\x{FE35}\x{FE37}\x{FE39}\x{FE3B}\x{FE3D}\x{FE3F}' .
'\x{FE41}\x{FE43}\x{FE47}\x{FE59}\x{FE5B}\x{FE5D}\x{FF08}' .
'\x{FF3B}\x{FF5B}\x{FF5F}\x{FF62}' .


















'\x{0000}\x{0001}\x{0002}\x{0003}\x{0004}\x{0005}\x{0006}' .
'\x{0007}\x{0008}\x{0009}\x{000A}\x{000B}\x{000C}\x{000D}' .
'\x{000E}\x{000F}\x{0010}\x{0011}\x{0012}\x{0013}\x{0014}' .
'\x{0015}\x{0016}\x{0017}\x{0018}\x{0019}\x{001A}\x{001B}' .
'\x{001C}\x{001D}\x{001E}\x{001F}\x{007F}\x{0080}\x{0081}' .
'\x{0082}\x{0083}\x{0084}\x{0085}\x{0086}\x{0087}\x{0088}' .
'\x{0089}\x{008A}\x{008B}\x{008C}\x{008D}\x{008E}\x{008F}' .
'\x{0090}\x{0091}\x{0092}\x{0093}\x{0094}\x{0095}\x{0096}' .
'\x{0097}\x{0098}\x{0099}\x{009A}\x{009B}\x{009C}\x{009D}' .
'\x{009E}\x{009F}' .
















'\x{00AD}\x{0600}\x{0601}\x{0602}\x{0603}\x{0604}\x{061C}' .
'\x{06DD}\x{070F}\x{180E}\x{200B}\x{200C}\x{200D}\x{200E}' .
'\x{200F}\x{202A}\x{202B}\x{202C}\x{202D}\x{202E}\x{2060}' .
'\x{2061}\x{2062}\x{2063}\x{2064}\x{2066}\x{2067}\x{2068}' .
'\x{2069}\x{206A}\x{206B}\x{206C}\x{206D}\x{206E}\x{206F}' .
'\x{FEFF}\x{FFF9}\x{FFFA}\x{FFFB}\x{110BD}\x{1D173}\x{1D174}' .
'\x{1D175}\x{1D176}\x{1D177}\x{1D178}\x{1D179}\x{1D17A}\x{E0001}' .
'\x{E0020}\x{E0021}\x{E0022}\x{E0023}\x{E0024}\x{E0025}\x{E0026}' .
'\x{E0027}\x{E0028}\x{E0029}\x{E002A}\x{E002B}\x{E002C}\x{E002D}' .
'\x{E002E}\x{E002F}\x{E0030}\x{E0031}\x{E0032}\x{E0033}\x{E0034}' .
'\x{E0035}\x{E0036}\x{E0037}\x{E0038}\x{E0039}\x{E003A}\x{E003B}' .
'\x{E003C}\x{E003D}\x{E003E}\x{E003F}\x{E0040}\x{E0041}\x{E0042}' .
'\x{E0043}\x{E0044}\x{E0045}\x{E0046}\x{E0047}\x{E0048}\x{E0049}' .
'\x{E004A}\x{E004B}\x{E004C}\x{E004D}\x{E004E}\x{E004F}\x{E0050}' .
'\x{E0051}\x{E0052}\x{E0053}\x{E0054}\x{E0055}\x{E0056}\x{E0057}' .
'\x{E0058}\x{E0059}\x{E005A}\x{E005B}\x{E005C}\x{E005D}\x{E005E}' .
'\x{E005F}\x{E0060}\x{E0061}\x{E0062}\x{E0063}\x{E0064}\x{E0065}' .
'\x{E0066}\x{E0067}\x{E0068}\x{E0069}\x{E006A}\x{E006B}\x{E006C}' .
'\x{E006D}\x{E006E}\x{E006F}\x{E0070}\x{E0071}\x{E0072}\x{E0073}' .
'\x{E0074}\x{E0075}\x{E0076}\x{E0077}\x{E0078}\x{E0079}\x{E007A}' .
'\x{E007B}\x{E007C}\x{E007D}\x{E007E}\x{E007F}' .



















'\x{E000}\x{F8FF}\x{F0000}\x{FFFFD}\x{100000}\x{10FFFD}' .




















'\x{D800}\x{DB7F}\x{DB80}\x{DBFF}\x{DC00}\x{DFFF}' .




















'\x{01C5}\x{01C8}\x{01CB}\x{01F2}\x{1F88}\x{1F89}\x{1F8A}' .
'\x{1F8B}\x{1F8C}\x{1F8D}\x{1F8E}\x{1F8F}\x{1F98}\x{1F99}' .
'\x{1F9A}\x{1F9B}\x{1F9C}\x{1F9D}\x{1F9E}\x{1F9F}\x{1FA8}' .
'\x{1FA9}\x{1FAA}\x{1FAB}\x{1FAC}\x{1FAD}\x{1FAE}\x{1FAF}' .
'\x{1FBC}\x{1FCC}\x{1FFC}' .

















'\x{0903}\x{093B}\x{093E}\x{093F}\x{0940}\x{0949}\x{094A}' .
'\x{094B}\x{094C}\x{094E}\x{094F}\x{0982}\x{0983}\x{09BE}' .
'\x{09BF}\x{09C0}\x{09C7}\x{09C8}\x{09CB}\x{09CC}\x{09D7}' .
'\x{0A03}\x{0A3E}\x{0A3F}\x{0A40}\x{0A83}\x{0ABE}\x{0ABF}' .
'\x{0AC0}\x{0AC9}\x{0ACB}\x{0ACC}\x{0B02}\x{0B03}\x{0B3E}' .
'\x{0B40}\x{0B47}\x{0B48}\x{0B4B}\x{0B4C}\x{0B57}\x{0BBE}' .
'\x{0BBF}\x{0BC1}\x{0BC2}\x{0BC6}\x{0BC7}\x{0BC8}\x{0BCA}' .
'\x{0BCB}\x{0BCC}\x{0BD7}\x{0C01}\x{0C02}\x{0C03}\x{0C41}' .
'\x{0C42}\x{0C43}\x{0C44}\x{0C82}\x{0C83}\x{0CBE}\x{0CC0}' .
'\x{0CC1}\x{0CC2}\x{0CC3}\x{0CC4}\x{0CC7}\x{0CC8}\x{0CCA}' .
'\x{0CCB}\x{0CD5}\x{0CD6}\x{0D02}\x{0D03}\x{0D3E}\x{0D3F}' .
'\x{0D40}\x{0D46}\x{0D47}\x{0D48}\x{0D4A}\x{0D4B}\x{0D4C}' .
'\x{0D57}\x{0D82}\x{0D83}\x{0DCF}\x{0DD0}\x{0DD1}\x{0DD8}' .
'\x{0DD9}\x{0DDA}\x{0DDB}\x{0DDC}\x{0DDD}\x{0DDE}\x{0DDF}' .
'\x{0DF2}\x{0DF3}\x{0F3E}\x{0F3F}\x{0F7F}\x{102B}\x{102C}' .
'\x{1031}\x{1038}\x{103B}\x{103C}\x{1056}\x{1057}\x{1062}' .
'\x{1063}\x{1064}\x{1067}\x{1068}\x{1069}\x{106A}\x{106B}' .
'\x{106C}\x{106D}\x{1083}\x{1084}\x{1087}\x{1088}\x{1089}' .
'\x{108A}\x{108B}\x{108C}\x{108F}\x{109A}\x{109B}\x{109C}' .
'\x{17B6}\x{17BE}\x{17BF}\x{17C0}\x{17C1}\x{17C2}\x{17C3}' .
'\x{17C4}\x{17C5}\x{17C7}\x{17C8}\x{1923}\x{1924}\x{1925}' .
'\x{1926}\x{1929}\x{192A}\x{192B}\x{1930}\x{1931}\x{1933}' .
'\x{1934}\x{1935}\x{1936}\x{1937}\x{1938}\x{19B0}\x{19B1}' .
'\x{19B2}\x{19B3}\x{19B4}\x{19B5}\x{19B6}\x{19B7}\x{19B8}' .
'\x{19B9}\x{19BA}\x{19BB}\x{19BC}\x{19BD}\x{19BE}\x{19BF}' .
'\x{19C0}\x{19C8}\x{19C9}\x{1A19}\x{1A1A}\x{1A55}\x{1A57}' .
'\x{1A61}\x{1A63}\x{1A64}\x{1A6D}\x{1A6E}\x{1A6F}\x{1A70}' .
'\x{1A71}\x{1A72}\x{1B04}\x{1B35}\x{1B3B}\x{1B3D}\x{1B3E}' .
'\x{1B3F}\x{1B40}\x{1B41}\x{1B43}\x{1B44}\x{1B82}\x{1BA1}' .
'\x{1BA6}\x{1BA7}\x{1BAA}\x{1BAC}\x{1BAD}\x{1BE7}\x{1BEA}' .
'\x{1BEB}\x{1BEC}\x{1BEE}\x{1BF2}\x{1BF3}\x{1C24}\x{1C25}' .
'\x{1C26}\x{1C27}\x{1C28}\x{1C29}\x{1C2A}\x{1C2B}\x{1C34}' .
'\x{1C35}\x{1CE1}\x{1CF2}\x{1CF3}\x{302E}\x{302F}\x{A823}' .
'\x{A824}\x{A827}\x{A880}\x{A881}\x{A8B4}\x{A8B5}\x{A8B6}' .
'\x{A8B7}\x{A8B8}\x{A8B9}\x{A8BA}\x{A8BB}\x{A8BC}\x{A8BD}' .
'\x{A8BE}\x{A8BF}\x{A8C0}\x{A8C1}\x{A8C2}\x{A8C3}\x{A952}' .
'\x{A953}\x{A983}\x{A9B4}\x{A9B5}\x{A9BA}\x{A9BB}\x{A9BD}' .
'\x{A9BE}\x{A9BF}\x{A9C0}\x{AA2F}\x{AA30}\x{AA33}\x{AA34}' .
'\x{AA4D}\x{AA7B}\x{AAEB}\x{AAEE}\x{AAEF}\x{AAF5}\x{ABE3}' .
'\x{ABE4}\x{ABE6}\x{ABE7}\x{ABE9}\x{ABEA}\x{ABEC}\x{11000}' .
'\x{11002}\x{11082}\x{110B0}\x{110B1}\x{110B2}\x{110B7}\x{110B8}' .
'\x{1112C}\x{11182}\x{111B3}\x{111B4}\x{111B5}\x{111BF}\x{111C0}' .
'\x{116AC}\x{116AE}\x{116AF}\x{116B6}\x{16F51}\x{16F52}\x{16F53}' .
'\x{16F54}\x{16F55}\x{16F56}\x{16F57}\x{16F58}\x{16F59}\x{16F5A}' .
'\x{16F5B}\x{16F5C}\x{16F5D}\x{16F5E}\x{16F5F}\x{16F60}\x{16F61}' .
'\x{16F62}\x{16F63}\x{16F64}\x{16F65}\x{16F66}\x{16F67}\x{16F68}' .
'\x{16F69}\x{16F6A}\x{16F6B}\x{16F6C}\x{16F6D}\x{16F6E}\x{16F6F}' .
'\x{16F70}\x{16F71}\x{16F72}\x{16F73}\x{16F74}\x{16F75}\x{16F76}' .
'\x{16F77}\x{16F78}\x{16F79}\x{16F7A}\x{16F7B}\x{16F7C}\x{16F7D}' .
'\x{16F7E}\x{1D165}\x{1D166}\x{1D16D}\x{1D16E}\x{1D16F}\x{1D170}' .
'\x{1D171}\x{1D172}' .





'\x{0488}\x{0489}\x{20DD}\x{20DE}\x{20DF}\x{20E0}\x{20E2}' .
'\x{20E3}\x{20E4}\x{A670}\x{A671}\x{A672}' .








'\x{0300}\x{0301}\x{0302}\x{0303}\x{0304}\x{0305}\x{0306}' .
'\x{0307}\x{0308}\x{0309}\x{030A}\x{030B}\x{030C}\x{030D}' .
'\x{030E}\x{030F}\x{0310}\x{0311}\x{0312}\x{0313}\x{0314}' .
'\x{0315}\x{0316}\x{0317}\x{0318}\x{0319}\x{031A}\x{031B}' .
'\x{031C}\x{031D}\x{031E}\x{031F}\x{0320}\x{0321}\x{0322}' .
'\x{0323}\x{0324}\x{0325}\x{0326}\x{0327}\x{0328}\x{0329}' .
'\x{032A}\x{032B}\x{032C}\x{032D}\x{032E}\x{032F}\x{0330}' .
'\x{0331}\x{0332}\x{0333}\x{0334}\x{0335}\x{0336}\x{0337}' .
'\x{0338}\x{0339}\x{033A}\x{033B}\x{033C}\x{033D}\x{033E}' .
'\x{033F}\x{0340}\x{0341}\x{0342}\x{0343}\x{0344}\x{0345}' .
'\x{0346}\x{0347}\x{0348}\x{0349}\x{034A}\x{034B}\x{034C}' .
'\x{034D}\x{034E}\x{034F}\x{0350}\x{0351}\x{0352}\x{0353}' .
'\x{0354}\x{0355}\x{0356}\x{0357}\x{0358}\x{0359}\x{035A}' .
'\x{035B}\x{035C}\x{035D}\x{035E}\x{035F}\x{0360}\x{0361}' .
'\x{0362}\x{0363}\x{0364}\x{0365}\x{0366}\x{0367}\x{0368}' .
'\x{0369}\x{036A}\x{036B}\x{036C}\x{036D}\x{036E}\x{036F}' .
'\x{0483}\x{0484}\x{0485}\x{0486}\x{0487}\x{0591}\x{0592}' .
'\x{0593}\x{0594}\x{0595}\x{0596}\x{0597}\x{0598}\x{0599}' .
'\x{059A}\x{059B}\x{059C}\x{059D}\x{059E}\x{059F}\x{05A0}' .
'\x{05A1}\x{05A2}\x{05A3}\x{05A4}\x{05A5}\x{05A6}\x{05A7}' .
'\x{05A8}\x{05A9}\x{05AA}\x{05AB}\x{05AC}\x{05AD}\x{05AE}' .
'\x{05AF}\x{05B0}\x{05B1}\x{05B2}\x{05B3}\x{05B4}\x{05B5}' .
'\x{05B6}\x{05B7}\x{05B8}\x{05B9}\x{05BA}\x{05BB}\x{05BC}' .
'\x{05BD}\x{05BF}\x{05C1}\x{05C2}\x{05C4}\x{05C5}\x{05C7}' .
'\x{0610}\x{0611}\x{0612}\x{0613}\x{0614}\x{0615}\x{0616}' .
'\x{0617}\x{0618}\x{0619}\x{061A}\x{064B}\x{064C}\x{064D}' .
'\x{064E}\x{064F}\x{0650}\x{0651}\x{0652}\x{0653}\x{0654}' .
'\x{0655}\x{0656}\x{0657}\x{0658}\x{0659}\x{065A}\x{065B}' .
'\x{065C}\x{065D}\x{065E}\x{065F}\x{0670}\x{06D6}\x{06D7}' .
'\x{06D8}\x{06D9}\x{06DA}\x{06DB}\x{06DC}\x{06DF}\x{06E0}' .
'\x{06E1}\x{06E2}\x{06E3}\x{06E4}\x{06E7}\x{06E8}\x{06EA}' .
'\x{06EB}\x{06EC}\x{06ED}\x{0711}\x{0730}\x{0731}\x{0732}' .
'\x{0733}\x{0734}\x{0735}\x{0736}\x{0737}\x{0738}\x{0739}' .
'\x{073A}\x{073B}\x{073C}\x{073D}\x{073E}\x{073F}\x{0740}' .
'\x{0741}\x{0742}\x{0743}\x{0744}\x{0745}\x{0746}\x{0747}' .
'\x{0748}\x{0749}\x{074A}\x{07A6}\x{07A7}\x{07A8}\x{07A9}' .
'\x{07AA}\x{07AB}\x{07AC}\x{07AD}\x{07AE}\x{07AF}\x{07B0}' .
'\x{07EB}\x{07EC}\x{07ED}\x{07EE}\x{07EF}\x{07F0}\x{07F1}' .
'\x{07F2}\x{07F3}\x{0816}\x{0817}\x{0818}\x{0819}\x{081B}' .
'\x{081C}\x{081D}\x{081E}\x{081F}\x{0820}\x{0821}\x{0822}' .
'\x{0823}\x{0825}\x{0826}\x{0827}\x{0829}\x{082A}\x{082B}' .
'\x{082C}\x{082D}\x{0859}\x{085A}\x{085B}\x{08E4}\x{08E5}' .
'\x{08E6}\x{08E7}\x{08E8}\x{08E9}\x{08EA}\x{08EB}\x{08EC}' .
'\x{08ED}\x{08EE}\x{08EF}\x{08F0}\x{08F1}\x{08F2}\x{08F3}' .
'\x{08F4}\x{08F5}\x{08F6}\x{08F7}\x{08F8}\x{08F9}\x{08FA}' .
'\x{08FB}\x{08FC}\x{08FD}\x{08FE}\x{0900}\x{0901}\x{0902}' .
'\x{093A}\x{093C}\x{0941}\x{0942}\x{0943}\x{0944}\x{0945}' .
'\x{0946}\x{0947}\x{0948}\x{094D}\x{0951}\x{0952}\x{0953}' .
'\x{0954}\x{0955}\x{0956}\x{0957}\x{0962}\x{0963}\x{0981}' .
'\x{09BC}\x{09C1}\x{09C2}\x{09C3}\x{09C4}\x{09CD}\x{09E2}' .
'\x{09E3}\x{0A01}\x{0A02}\x{0A3C}\x{0A41}\x{0A42}\x{0A47}' .
'\x{0A48}\x{0A4B}\x{0A4C}\x{0A4D}\x{0A51}\x{0A70}\x{0A71}' .
'\x{0A75}\x{0A81}\x{0A82}\x{0ABC}\x{0AC1}\x{0AC2}\x{0AC3}' .
'\x{0AC4}\x{0AC5}\x{0AC7}\x{0AC8}\x{0ACD}\x{0AE2}\x{0AE3}' .
'\x{0B01}\x{0B3C}\x{0B3F}\x{0B41}\x{0B42}\x{0B43}\x{0B44}' .
'\x{0B4D}\x{0B56}\x{0B62}\x{0B63}\x{0B82}\x{0BC0}\x{0BCD}' .
'\x{0C3E}\x{0C3F}\x{0C40}\x{0C46}\x{0C47}\x{0C48}\x{0C4A}' .
'\x{0C4B}\x{0C4C}\x{0C4D}\x{0C55}\x{0C56}\x{0C62}\x{0C63}' .
'\x{0CBC}\x{0CBF}\x{0CC6}\x{0CCC}\x{0CCD}\x{0CE2}\x{0CE3}' .
'\x{0D41}\x{0D42}\x{0D43}\x{0D44}\x{0D4D}\x{0D62}\x{0D63}' .
'\x{0DCA}\x{0DD2}\x{0DD3}\x{0DD4}\x{0DD6}\x{0E31}\x{0E34}' .
'\x{0E35}\x{0E36}\x{0E37}\x{0E38}\x{0E39}\x{0E3A}\x{0E47}' .
'\x{0E48}\x{0E49}\x{0E4A}\x{0E4B}\x{0E4C}\x{0E4D}\x{0E4E}' .
'\x{0EB1}\x{0EB4}\x{0EB5}\x{0EB6}\x{0EB7}\x{0EB8}\x{0EB9}' .
'\x{0EBB}\x{0EBC}\x{0EC8}\x{0EC9}\x{0ECA}\x{0ECB}\x{0ECC}' .
'\x{0ECD}\x{0F18}\x{0F19}\x{0F35}\x{0F37}\x{0F39}\x{0F71}' .
'\x{0F72}\x{0F73}\x{0F74}\x{0F75}\x{0F76}\x{0F77}\x{0F78}' .
'\x{0F79}\x{0F7A}\x{0F7B}\x{0F7C}\x{0F7D}\x{0F7E}\x{0F80}' .
'\x{0F81}\x{0F82}\x{0F83}\x{0F84}\x{0F86}\x{0F87}\x{0F8D}' .
'\x{0F8E}\x{0F8F}\x{0F90}\x{0F91}\x{0F92}\x{0F93}\x{0F94}' .
'\x{0F95}\x{0F96}\x{0F97}\x{0F99}\x{0F9A}\x{0F9B}\x{0F9C}' .
'\x{0F9D}\x{0F9E}\x{0F9F}\x{0FA0}\x{0FA1}\x{0FA2}\x{0FA3}' .
'\x{0FA4}\x{0FA5}\x{0FA6}\x{0FA7}\x{0FA8}\x{0FA9}\x{0FAA}' .
'\x{0FAB}\x{0FAC}\x{0FAD}\x{0FAE}\x{0FAF}\x{0FB0}\x{0FB1}' .
'\x{0FB2}\x{0FB3}\x{0FB4}\x{0FB5}\x{0FB6}\x{0FB7}\x{0FB8}' .
'\x{0FB9}\x{0FBA}\x{0FBB}\x{0FBC}\x{0FC6}\x{102D}\x{102E}' .
'\x{102F}\x{1030}\x{1032}\x{1033}\x{1034}\x{1035}\x{1036}' .
'\x{1037}\x{1039}\x{103A}\x{103D}\x{103E}\x{1058}\x{1059}' .
'\x{105E}\x{105F}\x{1060}\x{1071}\x{1072}\x{1073}\x{1074}' .
'\x{1082}\x{1085}\x{1086}\x{108D}\x{109D}\x{135D}\x{135E}' .
'\x{135F}\x{1712}\x{1713}\x{1714}\x{1732}\x{1733}\x{1734}' .
'\x{1752}\x{1753}\x{1772}\x{1773}\x{17B4}\x{17B5}\x{17B7}' .
'\x{17B8}\x{17B9}\x{17BA}\x{17BB}\x{17BC}\x{17BD}\x{17C6}' .
'\x{17C9}\x{17CA}\x{17CB}\x{17CC}\x{17CD}\x{17CE}\x{17CF}' .
'\x{17D0}\x{17D1}\x{17D2}\x{17D3}\x{17DD}\x{180B}\x{180C}' .
'\x{180D}\x{18A9}\x{1920}\x{1921}\x{1922}\x{1927}\x{1928}' .
'\x{1932}\x{1939}\x{193A}\x{193B}\x{1A17}\x{1A18}\x{1A1B}' .
'\x{1A56}\x{1A58}\x{1A59}\x{1A5A}\x{1A5B}\x{1A5C}\x{1A5D}' .
'\x{1A5E}\x{1A60}\x{1A62}\x{1A65}\x{1A66}\x{1A67}\x{1A68}' .
'\x{1A69}\x{1A6A}\x{1A6B}\x{1A6C}\x{1A73}\x{1A74}\x{1A75}' .
'\x{1A76}\x{1A77}\x{1A78}\x{1A79}\x{1A7A}\x{1A7B}\x{1A7C}' .
'\x{1A7F}\x{1B00}\x{1B01}\x{1B02}\x{1B03}\x{1B34}\x{1B36}' .
'\x{1B37}\x{1B38}\x{1B39}\x{1B3A}\x{1B3C}\x{1B42}\x{1B6B}' .
'\x{1B6C}\x{1B6D}\x{1B6E}\x{1B6F}\x{1B70}\x{1B71}\x{1B72}' .
'\x{1B73}\x{1B80}\x{1B81}\x{1BA2}\x{1BA3}\x{1BA4}\x{1BA5}' .
'\x{1BA8}\x{1BA9}\x{1BAB}\x{1BE6}\x{1BE8}\x{1BE9}\x{1BED}' .
'\x{1BEF}\x{1BF0}\x{1BF1}\x{1C2C}\x{1C2D}\x{1C2E}\x{1C2F}' .
'\x{1C30}\x{1C31}\x{1C32}\x{1C33}\x{1C36}\x{1C37}\x{1CD0}' .
'\x{1CD1}\x{1CD2}\x{1CD4}\x{1CD5}\x{1CD6}\x{1CD7}\x{1CD8}' .
'\x{1CD9}\x{1CDA}\x{1CDB}\x{1CDC}\x{1CDD}\x{1CDE}\x{1CDF}' .
'\x{1CE0}\x{1CE2}\x{1CE3}\x{1CE4}\x{1CE5}\x{1CE6}\x{1CE7}' .
'\x{1CE8}\x{1CED}\x{1CF4}\x{1DC0}\x{1DC1}\x{1DC2}\x{1DC3}' .
'\x{1DC4}\x{1DC5}\x{1DC6}\x{1DC7}\x{1DC8}\x{1DC9}\x{1DCA}' .
'\x{1DCB}\x{1DCC}\x{1DCD}\x{1DCE}\x{1DCF}\x{1DD0}\x{1DD1}' .
'\x{1DD2}\x{1DD3}\x{1DD4}\x{1DD5}\x{1DD6}\x{1DD7}\x{1DD8}' .
'\x{1DD9}\x{1DDA}\x{1DDB}\x{1DDC}\x{1DDD}\x{1DDE}\x{1DDF}' .
'\x{1DE0}\x{1DE1}\x{1DE2}\x{1DE3}\x{1DE4}\x{1DE5}\x{1DE6}' .
'\x{1DFC}\x{1DFD}\x{1DFE}\x{1DFF}\x{20D0}\x{20D1}\x{20D2}' .
'\x{20D3}\x{20D4}\x{20D5}\x{20D6}\x{20D7}\x{20D8}\x{20D9}' .
'\x{20DA}\x{20DB}\x{20DC}\x{20E1}\x{20E5}\x{20E6}\x{20E7}' .
'\x{20E8}\x{20E9}\x{20EA}\x{20EB}\x{20EC}\x{20ED}\x{20EE}' .
'\x{20EF}\x{20F0}\x{2CEF}\x{2CF0}\x{2CF1}\x{2D7F}\x{2DE0}' .
'\x{2DE1}\x{2DE2}\x{2DE3}\x{2DE4}\x{2DE5}\x{2DE6}\x{2DE7}' .
'\x{2DE8}\x{2DE9}\x{2DEA}\x{2DEB}\x{2DEC}\x{2DED}\x{2DEE}' .
'\x{2DEF}\x{2DF0}\x{2DF1}\x{2DF2}\x{2DF3}\x{2DF4}\x{2DF5}' .
'\x{2DF6}\x{2DF7}\x{2DF8}\x{2DF9}\x{2DFA}\x{2DFB}\x{2DFC}' .
'\x{2DFD}\x{2DFE}\x{2DFF}\x{302A}\x{302B}\x{302C}\x{302D}' .
'\x{3099}\x{309A}\x{A66F}\x{A674}\x{A675}\x{A676}\x{A677}' .
'\x{A678}\x{A679}\x{A67A}\x{A67B}\x{A67C}\x{A67D}\x{A69F}' .
'\x{A6F0}\x{A6F1}\x{A802}\x{A806}\x{A80B}\x{A825}\x{A826}' .
'\x{A8C4}\x{A8E0}\x{A8E1}\x{A8E2}\x{A8E3}\x{A8E4}\x{A8E5}' .
'\x{A8E6}\x{A8E7}\x{A8E8}\x{A8E9}\x{A8EA}\x{A8EB}\x{A8EC}' .
'\x{A8ED}\x{A8EE}\x{A8EF}\x{A8F0}\x{A8F1}\x{A926}\x{A927}' .
'\x{A928}\x{A929}\x{A92A}\x{A92B}\x{A92C}\x{A92D}\x{A947}' .
'\x{A948}\x{A949}\x{A94A}\x{A94B}\x{A94C}\x{A94D}\x{A94E}' .
'\x{A94F}\x{A950}\x{A951}\x{A980}\x{A981}\x{A982}\x{A9B3}' .
'\x{A9B6}\x{A9B7}\x{A9B8}\x{A9B9}\x{A9BC}\x{AA29}\x{AA2A}' .
'\x{AA2B}\x{AA2C}\x{AA2D}\x{AA2E}\x{AA31}\x{AA32}\x{AA35}' .
'\x{AA36}\x{AA43}\x{AA4C}\x{AAB0}\x{AAB2}\x{AAB3}\x{AAB4}' .
'\x{AAB7}\x{AAB8}\x{AABE}\x{AABF}\x{AAC1}\x{AAEC}\x{AAED}' .
'\x{AAF6}\x{ABE5}\x{ABE8}\x{ABED}\x{FB1E}\x{FE00}\x{FE01}' .
'\x{FE02}\x{FE03}\x{FE04}\x{FE05}\x{FE06}\x{FE07}\x{FE08}' .
'\x{FE09}\x{FE0A}\x{FE0B}\x{FE0C}\x{FE0D}\x{FE0E}\x{FE0F}' .
'\x{FE20}\x{FE21}\x{FE22}\x{FE23}\x{FE24}\x{FE25}\x{FE26}' .
'\x{101FD}\x{10A01}\x{10A02}\x{10A03}\x{10A05}\x{10A06}\x{10A0C}' .
'\x{10A0D}\x{10A0E}\x{10A0F}\x{10A38}\x{10A39}\x{10A3A}\x{10A3F}' .
'\x{11001}\x{11038}\x{11039}\x{1103A}\x{1103B}\x{1103C}\x{1103D}' .
'\x{1103E}\x{1103F}\x{11040}\x{11041}\x{11042}\x{11043}\x{11044}' .
'\x{11045}\x{11046}\x{11080}\x{11081}\x{110B3}\x{110B4}\x{110B5}' .
'\x{110B6}\x{110B9}\x{110BA}\x{11100}\x{11101}\x{11102}\x{11127}' .
'\x{11128}\x{11129}\x{1112A}\x{1112B}\x{1112D}\x{1112E}\x{1112F}' .
'\x{11130}\x{11131}\x{11132}\x{11133}\x{11134}\x{11180}\x{11181}' .
'\x{111B6}\x{111B7}\x{111B8}\x{111B9}\x{111BA}\x{111BB}\x{111BC}' .
'\x{111BD}\x{111BE}\x{116AB}\x{116AD}\x{116B0}\x{116B1}\x{116B2}' .
'\x{116B3}\x{116B4}\x{116B5}\x{116B7}\x{16F8F}\x{16F90}\x{16F91}' .
'\x{16F92}\x{1D167}\x{1D168}\x{1D169}\x{1D17B}\x{1D17C}\x{1D17D}' .
'\x{1D17E}\x{1D17F}\x{1D180}\x{1D181}\x{1D182}\x{1D185}\x{1D186}' .
'\x{1D187}\x{1D188}\x{1D189}\x{1D18A}\x{1D18B}\x{1D1AA}\x{1D1AB}' .
'\x{1D1AC}\x{1D1AD}\x{1D242}\x{1D243}\x{1D244}\x{E0100}\x{E0101}' .
'\x{E0102}\x{E0103}\x{E0104}\x{E0105}\x{E0106}\x{E0107}\x{E0108}' .
'\x{E0109}\x{E010A}\x{E010B}\x{E010C}\x{E010D}\x{E010E}\x{E010F}' .
'\x{E0110}\x{E0111}\x{E0112}\x{E0113}\x{E0114}\x{E0115}\x{E0116}' .
'\x{E0117}\x{E0118}\x{E0119}\x{E011A}\x{E011B}\x{E011C}\x{E011D}' .
'\x{E011E}\x{E011F}\x{E0120}\x{E0121}\x{E0122}\x{E0123}\x{E0124}' .
'\x{E0125}\x{E0126}\x{E0127}\x{E0128}\x{E0129}\x{E012A}\x{E012B}' .
'\x{E012C}\x{E012D}\x{E012E}\x{E012F}\x{E0130}\x{E0131}\x{E0132}' .
'\x{E0133}\x{E0134}\x{E0135}\x{E0136}\x{E0137}\x{E0138}\x{E0139}' .
'\x{E013A}\x{E013B}\x{E013C}\x{E013D}\x{E013E}\x{E013F}\x{E0140}' .
'\x{E0141}\x{E0142}\x{E0143}\x{E0144}\x{E0145}\x{E0146}\x{E0147}' .
'\x{E0148}\x{E0149}\x{E014A}\x{E014B}\x{E014C}\x{E014D}\x{E014E}' .
'\x{E014F}\x{E0150}\x{E0151}\x{E0152}\x{E0153}\x{E0154}\x{E0155}' .
'\x{E0156}\x{E0157}\x{E0158}\x{E0159}\x{E015A}\x{E015B}\x{E015C}' .
'\x{E015D}\x{E015E}\x{E015F}\x{E0160}\x{E0161}\x{E0162}\x{E0163}' .
'\x{E0164}\x{E0165}\x{E0166}\x{E0167}\x{E0168}\x{E0169}\x{E016A}' .
'\x{E016B}\x{E016C}\x{E016D}\x{E016E}\x{E016F}\x{E0170}\x{E0171}' .
'\x{E0172}\x{E0173}\x{E0174}\x{E0175}\x{E0176}\x{E0177}\x{E0178}' .
'\x{E0179}\x{E017A}\x{E017B}\x{E017C}\x{E017D}\x{E017E}\x{E017F}' .
'\x{E0180}\x{E0181}\x{E0182}\x{E0183}\x{E0184}\x{E0185}\x{E0186}' .
'\x{E0187}\x{E0188}\x{E0189}\x{E018A}\x{E018B}\x{E018C}\x{E018D}' .
'\x{E018E}\x{E018F}\x{E0190}\x{E0191}\x{E0192}\x{E0193}\x{E0194}' .
'\x{E0195}\x{E0196}\x{E0197}\x{E0198}\x{E0199}\x{E019A}\x{E019B}' .
'\x{E019C}\x{E019D}\x{E019E}\x{E019F}\x{E01A0}\x{E01A1}\x{E01A2}' .
'\x{E01A3}\x{E01A4}\x{E01A5}\x{E01A6}\x{E01A7}\x{E01A8}\x{E01A9}' .
'\x{E01AA}\x{E01AB}\x{E01AC}\x{E01AD}\x{E01AE}\x{E01AF}\x{E01B0}' .
'\x{E01B1}\x{E01B2}\x{E01B3}\x{E01B4}\x{E01B5}\x{E01B6}\x{E01B7}' .
'\x{E01B8}\x{E01B9}\x{E01BA}\x{E01BB}\x{E01BC}\x{E01BD}\x{E01BE}' .
'\x{E01BF}\x{E01C0}\x{E01C1}\x{E01C2}\x{E01C3}\x{E01C4}\x{E01C5}' .
'\x{E01C6}\x{E01C7}\x{E01C8}\x{E01C9}\x{E01CA}\x{E01CB}\x{E01CC}' .
'\x{E01CD}\x{E01CE}\x{E01CF}\x{E01D0}\x{E01D1}\x{E01D2}\x{E01D3}' .
'\x{E01D4}\x{E01D5}\x{E01D6}\x{E01D7}\x{E01D8}\x{E01D9}\x{E01DA}' .
'\x{E01DB}\x{E01DC}\x{E01DD}\x{E01DE}\x{E01DF}\x{E01E0}\x{E01E1}' .
'\x{E01E2}\x{E01E3}\x{E01E4}\x{E01E5}\x{E01E6}\x{E01E7}\x{E01E8}' .
'\x{E01E9}\x{E01EA}\x{E01EB}\x{E01EC}\x{E01ED}\x{E01EE}\x{E01EF}' .










'\x{0030}\x{0031}\x{0032}\x{0033}\x{0034}\x{0035}\x{0036}' .
'\x{0037}\x{0038}\x{0039}\x{0660}\x{0661}\x{0662}\x{0663}' .
'\x{0664}\x{0665}\x{0666}\x{0667}\x{0668}\x{0669}\x{06F0}' .
'\x{06F1}\x{06F2}\x{06F3}\x{06F4}\x{06F5}\x{06F6}\x{06F7}' .
'\x{06F8}\x{06F9}\x{07C0}\x{07C1}\x{07C2}\x{07C3}\x{07C4}' .
'\x{07C5}\x{07C6}\x{07C7}\x{07C8}\x{07C9}\x{0966}\x{0967}' .
'\x{0968}\x{0969}\x{096A}\x{096B}\x{096C}\x{096D}\x{096E}' .
'\x{096F}\x{09E6}\x{09E7}\x{09E8}\x{09E9}\x{09EA}\x{09EB}' .
'\x{09EC}\x{09ED}\x{09EE}\x{09EF}\x{0A66}\x{0A67}\x{0A68}' .
'\x{0A69}\x{0A6A}\x{0A6B}\x{0A6C}\x{0A6D}\x{0A6E}\x{0A6F}' .
'\x{0AE6}\x{0AE7}\x{0AE8}\x{0AE9}\x{0AEA}\x{0AEB}\x{0AEC}' .
'\x{0AED}\x{0AEE}\x{0AEF}\x{0B66}\x{0B67}\x{0B68}\x{0B69}' .
'\x{0B6A}\x{0B6B}\x{0B6C}\x{0B6D}\x{0B6E}\x{0B6F}\x{0BE6}' .
'\x{0BE7}\x{0BE8}\x{0BE9}\x{0BEA}\x{0BEB}\x{0BEC}\x{0BED}' .
'\x{0BEE}\x{0BEF}\x{0C66}\x{0C67}\x{0C68}\x{0C69}\x{0C6A}' .
'\x{0C6B}\x{0C6C}\x{0C6D}\x{0C6E}\x{0C6F}\x{0CE6}\x{0CE7}' .
'\x{0CE8}\x{0CE9}\x{0CEA}\x{0CEB}\x{0CEC}\x{0CED}\x{0CEE}' .
'\x{0CEF}\x{0D66}\x{0D67}\x{0D68}\x{0D69}\x{0D6A}\x{0D6B}' .
'\x{0D6C}\x{0D6D}\x{0D6E}\x{0D6F}\x{0E50}\x{0E51}\x{0E52}' .
'\x{0E53}\x{0E54}\x{0E55}\x{0E56}\x{0E57}\x{0E58}\x{0E59}' .
'\x{0ED0}\x{0ED1}\x{0ED2}\x{0ED3}\x{0ED4}\x{0ED5}\x{0ED6}' .
'\x{0ED7}\x{0ED8}\x{0ED9}\x{0F20}\x{0F21}\x{0F22}\x{0F23}' .
'\x{0F24}\x{0F25}\x{0F26}\x{0F27}\x{0F28}\x{0F29}\x{1040}' .
'\x{1041}\x{1042}\x{1043}\x{1044}\x{1045}\x{1046}\x{1047}' .
'\x{1048}\x{1049}\x{1090}\x{1091}\x{1092}\x{1093}\x{1094}' .
'\x{1095}\x{1096}\x{1097}\x{1098}\x{1099}\x{17E0}\x{17E1}' .
'\x{17E2}\x{17E3}\x{17E4}\x{17E5}\x{17E6}\x{17E7}\x{17E8}' .
'\x{17E9}\x{1810}\x{1811}\x{1812}\x{1813}\x{1814}\x{1815}' .
'\x{1816}\x{1817}\x{1818}\x{1819}\x{1946}\x{1947}\x{1948}' .
'\x{1949}\x{194A}\x{194B}\x{194C}\x{194D}\x{194E}\x{194F}' .
'\x{19D0}\x{19D1}\x{19D2}\x{19D3}\x{19D4}\x{19D5}\x{19D6}' .
'\x{19D7}\x{19D8}\x{19D9}\x{1A80}\x{1A81}\x{1A82}\x{1A83}' .
'\x{1A84}\x{1A85}\x{1A86}\x{1A87}\x{1A88}\x{1A89}\x{1A90}' .
'\x{1A91}\x{1A92}\x{1A93}\x{1A94}\x{1A95}\x{1A96}\x{1A97}' .
'\x{1A98}\x{1A99}\x{1B50}\x{1B51}\x{1B52}\x{1B53}\x{1B54}' .
'\x{1B55}\x{1B56}\x{1B57}\x{1B58}\x{1B59}\x{1BB0}\x{1BB1}' .
'\x{1BB2}\x{1BB3}\x{1BB4}\x{1BB5}\x{1BB6}\x{1BB7}\x{1BB8}' .
'\x{1BB9}\x{1C40}\x{1C41}\x{1C42}\x{1C43}\x{1C44}\x{1C45}' .
'\x{1C46}\x{1C47}\x{1C48}\x{1C49}\x{1C50}\x{1C51}\x{1C52}' .
'\x{1C53}\x{1C54}\x{1C55}\x{1C56}\x{1C57}\x{1C58}\x{1C59}' .
'\x{A620}\x{A621}\x{A622}\x{A623}\x{A624}\x{A625}\x{A626}' .
'\x{A627}\x{A628}\x{A629}\x{A8D0}\x{A8D1}\x{A8D2}\x{A8D3}' .
'\x{A8D4}\x{A8D5}\x{A8D6}\x{A8D7}\x{A8D8}\x{A8D9}\x{A900}' .
'\x{A901}\x{A902}\x{A903}\x{A904}\x{A905}\x{A906}\x{A907}' .
'\x{A908}\x{A909}\x{A9D0}\x{A9D1}\x{A9D2}\x{A9D3}\x{A9D4}' .
'\x{A9D5}\x{A9D6}\x{A9D7}\x{A9D8}\x{A9D9}\x{AA50}\x{AA51}' .
'\x{AA52}\x{AA53}\x{AA54}\x{AA55}\x{AA56}\x{AA57}\x{AA58}' .
'\x{AA59}\x{ABF0}\x{ABF1}\x{ABF2}\x{ABF3}\x{ABF4}\x{ABF5}' .
'\x{ABF6}\x{ABF7}\x{ABF8}\x{ABF9}\x{FF10}\x{FF11}\x{FF12}' .
'\x{FF13}\x{FF14}\x{FF15}\x{FF16}\x{FF17}\x{FF18}\x{FF19}' .
'\x{104A0}\x{104A1}\x{104A2}\x{104A3}\x{104A4}\x{104A5}\x{104A6}' .
'\x{104A7}\x{104A8}\x{104A9}\x{11066}\x{11067}\x{11068}\x{11069}' .
'\x{1106A}\x{1106B}\x{1106C}\x{1106D}\x{1106E}\x{1106F}\x{110F0}' .
'\x{110F1}\x{110F2}\x{110F3}\x{110F4}\x{110F5}\x{110F6}\x{110F7}' .
'\x{110F8}\x{110F9}\x{11136}\x{11137}\x{11138}\x{11139}\x{1113A}' .
'\x{1113B}\x{1113C}\x{1113D}\x{1113E}\x{1113F}\x{111D0}\x{111D1}' .
'\x{111D2}\x{111D3}\x{111D4}\x{111D5}\x{111D6}\x{111D7}\x{111D8}' .
'\x{111D9}\x{116C0}\x{116C1}\x{116C2}\x{116C3}\x{116C4}\x{116C5}' .
'\x{116C6}\x{116C7}\x{116C8}\x{116C9}\x{1D7CE}\x{1D7CF}\x{1D7D0}' .
'\x{1D7D1}\x{1D7D2}\x{1D7D3}\x{1D7D4}\x{1D7D5}\x{1D7D6}\x{1D7D7}' .
'\x{1D7D8}\x{1D7D9}\x{1D7DA}\x{1D7DB}\x{1D7DC}\x{1D7DD}\x{1D7DE}' .
'\x{1D7DF}\x{1D7E0}\x{1D7E1}\x{1D7E2}\x{1D7E3}\x{1D7E4}\x{1D7E5}' .
'\x{1D7E6}\x{1D7E7}\x{1D7E8}\x{1D7E9}\x{1D7EA}\x{1D7EB}\x{1D7EC}' .
'\x{1D7ED}\x{1D7EE}\x{1D7EF}\x{1D7F0}\x{1D7F1}\x{1D7F2}\x{1D7F3}' .
'\x{1D7F4}\x{1D7F5}\x{1D7F6}\x{1D7F7}\x{1D7F8}\x{1D7F9}\x{1D7FA}' .
'\x{1D7FB}\x{1D7FC}\x{1D7FD}\x{1D7FE}\x{1D7FF}' .








'\x{16EE}\x{16EF}\x{16F0}\x{2160}\x{2161}\x{2162}\x{2163}' .
'\x{2164}\x{2165}\x{2166}\x{2167}\x{2168}\x{2169}\x{216A}' .
'\x{216B}\x{216C}\x{216D}\x{216E}\x{216F}\x{2170}\x{2171}' .
'\x{2172}\x{2173}\x{2174}\x{2175}\x{2176}\x{2177}\x{2178}' .
'\x{2179}\x{217A}\x{217B}\x{217C}\x{217D}\x{217E}\x{217F}' .
'\x{2180}\x{2181}\x{2182}\x{2185}\x{2186}\x{2187}\x{2188}' .
'\x{3007}\x{3021}\x{3022}\x{3023}\x{3024}\x{3025}\x{3026}' .
'\x{3027}\x{3028}\x{3029}\x{3038}\x{3039}\x{303A}\x{A6E6}' .
'\x{A6E7}\x{A6E8}\x{A6E9}\x{A6EA}\x{A6EB}\x{A6EC}\x{A6ED}' .
'\x{A6EE}\x{A6EF}\x{10140}\x{10141}\x{10142}\x{10143}\x{10144}' .
'\x{10145}\x{10146}\x{10147}\x{10148}\x{10149}\x{1014A}\x{1014B}' .
'\x{1014C}\x{1014D}\x{1014E}\x{1014F}\x{10150}\x{10151}\x{10152}' .
'\x{10153}\x{10154}\x{10155}\x{10156}\x{10157}\x{10158}\x{10159}' .
'\x{1015A}\x{1015B}\x{1015C}\x{1015D}\x{1015E}\x{1015F}\x{10160}' .
'\x{10161}\x{10162}\x{10163}\x{10164}\x{10165}\x{10166}\x{10167}' .
'\x{10168}\x{10169}\x{1016A}\x{1016B}\x{1016C}\x{1016D}\x{1016E}' .
'\x{1016F}\x{10170}\x{10171}\x{10172}\x{10173}\x{10174}\x{10341}' .
'\x{1034A}\x{103D1}\x{103D2}\x{103D3}\x{103D4}\x{103D5}\x{12400}' .
'\x{12401}\x{12402}\x{12403}\x{12404}\x{12405}\x{12406}\x{12407}' .
'\x{12408}\x{12409}\x{1240A}\x{1240B}\x{1240C}\x{1240D}\x{1240E}' .
'\x{1240F}\x{12410}\x{12411}\x{12412}\x{12413}\x{12414}\x{12415}' .
'\x{12416}\x{12417}\x{12418}\x{12419}\x{1241A}\x{1241B}\x{1241C}' .
'\x{1241D}\x{1241E}\x{1241F}\x{12420}\x{12421}\x{12422}\x{12423}' .
'\x{12424}\x{12425}\x{12426}\x{12427}\x{12428}\x{12429}\x{1242A}' .
'\x{1242B}\x{1242C}\x{1242D}\x{1242E}\x{1242F}\x{12430}\x{12431}' .
'\x{12432}\x{12433}\x{12434}\x{12435}\x{12436}\x{12437}\x{12438}' .
'\x{12439}\x{1243A}\x{1243B}\x{1243C}\x{1243D}\x{1243E}\x{1243F}' .
'\x{12440}\x{12441}\x{12442}\x{12443}\x{12444}\x{12445}\x{12446}' .
'\x{12447}\x{12448}\x{12449}\x{1244A}\x{1244B}\x{1244C}\x{1244D}' .
'\x{1244E}\x{1244F}\x{12450}\x{12451}\x{12452}\x{12453}\x{12454}' .
'\x{12455}\x{12456}\x{12457}\x{12458}\x{12459}\x{1245A}\x{1245B}' .
'\x{1245C}\x{1245D}\x{1245E}\x{1245F}\x{12460}\x{12461}\x{12462}' .










'\x{00B2}\x{00B3}\x{00B9}\x{00BC}\x{00BD}\x{00BE}\x{09F4}' .
'\x{09F5}\x{09F6}\x{09F7}\x{09F8}\x{09F9}\x{0B72}\x{0B73}' .
'\x{0B74}\x{0B75}\x{0B76}\x{0B77}\x{0BF0}\x{0BF1}\x{0BF2}' .
'\x{0C78}\x{0C79}\x{0C7A}\x{0C7B}\x{0C7C}\x{0C7D}\x{0C7E}' .
'\x{0D70}\x{0D71}\x{0D72}\x{0D73}\x{0D74}\x{0D75}\x{0F2A}' .
'\x{0F2B}\x{0F2C}\x{0F2D}\x{0F2E}\x{0F2F}\x{0F30}\x{0F31}' .
'\x{0F32}\x{0F33}\x{1369}\x{136A}\x{136B}\x{136C}\x{136D}' .
'\x{136E}\x{136F}\x{1370}\x{1371}\x{1372}\x{1373}\x{1374}' .
'\x{1375}\x{1376}\x{1377}\x{1378}\x{1379}\x{137A}\x{137B}' .
'\x{137C}\x{17F0}\x{17F1}\x{17F2}\x{17F3}\x{17F4}\x{17F5}' .
'\x{17F6}\x{17F7}\x{17F8}\x{17F9}\x{19DA}\x{2070}\x{2074}' .
'\x{2075}\x{2076}\x{2077}\x{2078}\x{2079}\x{2080}\x{2081}' .
'\x{2082}\x{2083}\x{2084}\x{2085}\x{2086}\x{2087}\x{2088}' .
'\x{2089}\x{2150}\x{2151}\x{2152}\x{2153}\x{2154}\x{2155}' .
'\x{2156}\x{2157}\x{2158}\x{2159}\x{215A}\x{215B}\x{215C}' .
'\x{215D}\x{215E}\x{215F}\x{2189}\x{2460}\x{2461}\x{2462}' .
'\x{2463}\x{2464}\x{2465}\x{2466}\x{2467}\x{2468}\x{2469}' .
'\x{246A}\x{246B}\x{246C}\x{246D}\x{246E}\x{246F}\x{2470}' .
'\x{2471}\x{2472}\x{2473}\x{2474}\x{2475}\x{2476}\x{2477}' .
'\x{2478}\x{2479}\x{247A}\x{247B}\x{247C}\x{247D}\x{247E}' .
'\x{247F}\x{2480}\x{2481}\x{2482}\x{2483}\x{2484}\x{2485}' .
'\x{2486}\x{2487}\x{2488}\x{2489}\x{248A}\x{248B}\x{248C}' .
'\x{248D}\x{248E}\x{248F}\x{2490}\x{2491}\x{2492}\x{2493}' .
'\x{2494}\x{2495}\x{2496}\x{2497}\x{2498}\x{2499}\x{249A}' .
'\x{249B}\x{24EA}\x{24EB}\x{24EC}\x{24ED}\x{24EE}\x{24EF}' .
'\x{24F0}\x{24F1}\x{24F2}\x{24F3}\x{24F4}\x{24F5}\x{24F6}' .
'\x{24F7}\x{24F8}\x{24F9}\x{24FA}\x{24FB}\x{24FC}\x{24FD}' .
'\x{24FE}\x{24FF}\x{2776}\x{2777}\x{2778}\x{2779}\x{277A}' .
'\x{277B}\x{277C}\x{277D}\x{277E}\x{277F}\x{2780}\x{2781}' .
'\x{2782}\x{2783}\x{2784}\x{2785}\x{2786}\x{2787}\x{2788}' .
'\x{2789}\x{278A}\x{278B}\x{278C}\x{278D}\x{278E}\x{278F}' .
'\x{2790}\x{2791}\x{2792}\x{2793}\x{2CFD}\x{3192}\x{3193}' .
'\x{3194}\x{3195}\x{3220}\x{3221}\x{3222}\x{3223}\x{3224}' .
'\x{3225}\x{3226}\x{3227}\x{3228}\x{3229}\x{3248}\x{3249}' .
'\x{324A}\x{324B}\x{324C}\x{324D}\x{324E}\x{324F}\x{3251}' .
'\x{3252}\x{3253}\x{3254}\x{3255}\x{3256}\x{3257}\x{3258}' .
'\x{3259}\x{325A}\x{325B}\x{325C}\x{325D}\x{325E}\x{325F}' .
'\x{3280}\x{3281}\x{3282}\x{3283}\x{3284}\x{3285}\x{3286}' .
'\x{3287}\x{3288}\x{3289}\x{32B1}\x{32B2}\x{32B3}\x{32B4}' .
'\x{32B5}\x{32B6}\x{32B7}\x{32B8}\x{32B9}\x{32BA}\x{32BB}' .
'\x{32BC}\x{32BD}\x{32BE}\x{32BF}\x{A830}\x{A831}\x{A832}' .
'\x{A833}\x{A834}\x{A835}\x{10107}\x{10108}\x{10109}\x{1010A}' .
'\x{1010B}\x{1010C}\x{1010D}\x{1010E}\x{1010F}\x{10110}\x{10111}' .
'\x{10112}\x{10113}\x{10114}\x{10115}\x{10116}\x{10117}\x{10118}' .
'\x{10119}\x{1011A}\x{1011B}\x{1011C}\x{1011D}\x{1011E}\x{1011F}' .
'\x{10120}\x{10121}\x{10122}\x{10123}\x{10124}\x{10125}\x{10126}' .
'\x{10127}\x{10128}\x{10129}\x{1012A}\x{1012B}\x{1012C}\x{1012D}' .
'\x{1012E}\x{1012F}\x{10130}\x{10131}\x{10132}\x{10133}\x{10175}' .
'\x{10176}\x{10177}\x{10178}\x{1018A}\x{10320}\x{10321}\x{10322}' .
'\x{10323}\x{10858}\x{10859}\x{1085A}\x{1085B}\x{1085C}\x{1085D}' .
'\x{1085E}\x{1085F}\x{10916}\x{10917}\x{10918}\x{10919}\x{1091A}' .
'\x{1091B}\x{10A40}\x{10A41}\x{10A42}\x{10A43}\x{10A44}\x{10A45}' .
'\x{10A46}\x{10A47}\x{10A7D}\x{10A7E}\x{10B58}\x{10B59}\x{10B5A}' .
'\x{10B5B}\x{10B5C}\x{10B5D}\x{10B5E}\x{10B5F}\x{10B78}\x{10B79}' .
'\x{10B7A}\x{10B7B}\x{10B7C}\x{10B7D}\x{10B7E}\x{10B7F}\x{10E60}' .
'\x{10E61}\x{10E62}\x{10E63}\x{10E64}\x{10E65}\x{10E66}\x{10E67}' .
'\x{10E68}\x{10E69}\x{10E6A}\x{10E6B}\x{10E6C}\x{10E6D}\x{10E6E}' .
'\x{10E6F}\x{10E70}\x{10E71}\x{10E72}\x{10E73}\x{10E74}\x{10E75}' .
'\x{10E76}\x{10E77}\x{10E78}\x{10E79}\x{10E7A}\x{10E7B}\x{10E7C}' .
'\x{10E7D}\x{10E7E}\x{11052}\x{11053}\x{11054}\x{11055}\x{11056}' .
'\x{11057}\x{11058}\x{11059}\x{1105A}\x{1105B}\x{1105C}\x{1105D}' .
'\x{1105E}\x{1105F}\x{11060}\x{11061}\x{11062}\x{11063}\x{11064}' .
'\x{11065}\x{1D360}\x{1D361}\x{1D362}\x{1D363}\x{1D364}\x{1D365}' .
'\x{1D366}\x{1D367}\x{1D368}\x{1D369}\x{1D36A}\x{1D36B}\x{1D36C}' .
'\x{1D36D}\x{1D36E}\x{1D36F}\x{1D370}\x{1D371}\x{1F100}\x{1F101}' .
'\x{1F102}\x{1F103}\x{1F104}\x{1F105}\x{1F106}\x{1F107}\x{1F108}' .
'\x{1F109}\x{1F10A}' .





'\x{0024}\x{00A2}\x{00A3}\x{00A4}\x{00A5}\x{058F}\x{060B}' .
'\x{09F2}\x{09F3}\x{09FB}\x{0AF1}\x{0BF9}\x{0E3F}\x{17DB}' .
'\x{20A0}\x{20A1}\x{20A2}\x{20A3}\x{20A4}\x{20A5}\x{20A6}' .
'\x{20A7}\x{20A8}\x{20A9}\x{20AA}\x{20AB}\x{20AC}\x{20AD}' .
'\x{20AE}\x{20AF}\x{20B0}\x{20B1}\x{20B2}\x{20B3}\x{20B4}' .
'\x{20B5}\x{20B6}\x{20B7}\x{20B8}\x{20B9}\x{20BA}\x{A838}' .
'\x{FDFC}\x{FE69}\x{FF04}\x{FFE0}\x{FFE1}\x{FFE5}\x{FFE6}' .










'\x{005E}\x{0060}\x{00A8}\x{00AF}\x{00B4}\x{00B8}\x{02C2}' .
'\x{02C3}\x{02C4}\x{02C5}\x{02D2}\x{02D3}\x{02D4}\x{02D5}' .
'\x{02D6}\x{02D7}\x{02D8}\x{02D9}\x{02DA}\x{02DB}\x{02DC}' .
'\x{02DD}\x{02DE}\x{02DF}\x{02E5}\x{02E6}\x{02E7}\x{02E8}' .
'\x{02E9}\x{02EA}\x{02EB}\x{02ED}\x{02EF}\x{02F0}\x{02F1}' .
'\x{02F2}\x{02F3}\x{02F4}\x{02F5}\x{02F6}\x{02F7}\x{02F8}' .
'\x{02F9}\x{02FA}\x{02FB}\x{02FC}\x{02FD}\x{02FE}\x{02FF}' .
'\x{0375}\x{0384}\x{0385}\x{1FBD}\x{1FBF}\x{1FC0}\x{1FC1}' .
'\x{1FCD}\x{1FCE}\x{1FCF}\x{1FDD}\x{1FDE}\x{1FDF}\x{1FED}' .
'\x{1FEE}\x{1FEF}\x{1FFD}\x{1FFE}\x{309B}\x{309C}\x{A700}' .
'\x{A701}\x{A702}\x{A703}\x{A704}\x{A705}\x{A706}\x{A707}' .
'\x{A708}\x{A709}\x{A70A}\x{A70B}\x{A70C}\x{A70D}\x{A70E}' .
'\x{A70F}\x{A710}\x{A711}\x{A712}\x{A713}\x{A714}\x{A715}' .
'\x{A716}\x{A720}\x{A721}\x{A789}\x{A78A}\x{FBB2}\x{FBB3}' .
'\x{FBB4}\x{FBB5}\x{FBB6}\x{FBB7}\x{FBB8}\x{FBB9}\x{FBBA}' .
'\x{FBBB}\x{FBBC}\x{FBBD}\x{FBBE}\x{FBBF}\x{FBC0}\x{FBC1}' .
'\x{FF3E}\x{FF40}\x{FFE3}' .






'\x{00A6}\x{00A9}\x{00AE}\x{00B0}\x{0482}\x{060E}\x{060F}' .
'\x{06DE}\x{06E9}\x{06FD}\x{06FE}\x{07F6}\x{09FA}\x{0B70}' .
'\x{0BF3}\x{0BF4}\x{0BF5}\x{0BF6}\x{0BF7}\x{0BF8}\x{0BFA}' .
'\x{0C7F}\x{0D79}\x{0F01}\x{0F02}\x{0F03}\x{0F13}\x{0F15}' .
'\x{0F16}\x{0F17}\x{0F1A}\x{0F1B}\x{0F1C}\x{0F1D}\x{0F1E}' .
'\x{0F1F}\x{0F34}\x{0F36}\x{0F38}\x{0FBE}\x{0FBF}\x{0FC0}' .
'\x{0FC1}\x{0FC2}\x{0FC3}\x{0FC4}\x{0FC5}\x{0FC7}\x{0FC8}' .
'\x{0FC9}\x{0FCA}\x{0FCB}\x{0FCC}\x{0FCE}\x{0FCF}\x{0FD5}' .
'\x{0FD6}\x{0FD7}\x{0FD8}\x{109E}\x{109F}\x{1390}\x{1391}' .
'\x{1392}\x{1393}\x{1394}\x{1395}\x{1396}\x{1397}\x{1398}' .
'\x{1399}\x{1940}\x{19DE}\x{19DF}\x{19E0}\x{19E1}\x{19E2}' .
'\x{19E3}\x{19E4}\x{19E5}\x{19E6}\x{19E7}\x{19E8}\x{19E9}' .
'\x{19EA}\x{19EB}\x{19EC}\x{19ED}\x{19EE}\x{19EF}\x{19F0}' .
'\x{19F1}\x{19F2}\x{19F3}\x{19F4}\x{19F5}\x{19F6}\x{19F7}' .
'\x{19F8}\x{19F9}\x{19FA}\x{19FB}\x{19FC}\x{19FD}\x{19FE}' .
'\x{19FF}\x{1B61}\x{1B62}\x{1B63}\x{1B64}\x{1B65}\x{1B66}' .
'\x{1B67}\x{1B68}\x{1B69}\x{1B6A}\x{1B74}\x{1B75}\x{1B76}' .
'\x{1B77}\x{1B78}\x{1B79}\x{1B7A}\x{1B7B}\x{1B7C}\x{2100}' .
'\x{2101}\x{2103}\x{2104}\x{2105}\x{2106}\x{2108}\x{2109}' .
'\x{2114}\x{2116}\x{2117}\x{211E}\x{211F}\x{2120}\x{2121}' .
'\x{2122}\x{2123}\x{2125}\x{2127}\x{2129}\x{212E}\x{213A}' .
'\x{213B}\x{214A}\x{214C}\x{214D}\x{214F}\x{2195}\x{2196}' .
'\x{2197}\x{2198}\x{2199}\x{219C}\x{219D}\x{219E}\x{219F}' .
'\x{21A1}\x{21A2}\x{21A4}\x{21A5}\x{21A7}\x{21A8}\x{21A9}' .
'\x{21AA}\x{21AB}\x{21AC}\x{21AD}\x{21AF}\x{21B0}\x{21B1}' .
'\x{21B2}\x{21B3}\x{21B4}\x{21B5}\x{21B6}\x{21B7}\x{21B8}' .
'\x{21B9}\x{21BA}\x{21BB}\x{21BC}\x{21BD}\x{21BE}\x{21BF}' .
'\x{21C0}\x{21C1}\x{21C2}\x{21C3}\x{21C4}\x{21C5}\x{21C6}' .
'\x{21C7}\x{21C8}\x{21C9}\x{21CA}\x{21CB}\x{21CC}\x{21CD}' .
'\x{21D0}\x{21D1}\x{21D3}\x{21D5}\x{21D6}\x{21D7}\x{21D8}' .
'\x{21D9}\x{21DA}\x{21DB}\x{21DC}\x{21DD}\x{21DE}\x{21DF}' .
'\x{21E0}\x{21E1}\x{21E2}\x{21E3}\x{21E4}\x{21E5}\x{21E6}' .
'\x{21E7}\x{21E8}\x{21E9}\x{21EA}\x{21EB}\x{21EC}\x{21ED}' .
'\x{21EE}\x{21EF}\x{21F0}\x{21F1}\x{21F2}\x{21F3}\x{2300}' .
'\x{2301}\x{2302}\x{2303}\x{2304}\x{2305}\x{2306}\x{2307}' .
'\x{230C}\x{230D}\x{230E}\x{230F}\x{2310}\x{2311}\x{2312}' .
'\x{2313}\x{2314}\x{2315}\x{2316}\x{2317}\x{2318}\x{2319}' .
'\x{231A}\x{231B}\x{231C}\x{231D}\x{231E}\x{231F}\x{2322}' .
'\x{2323}\x{2324}\x{2325}\x{2326}\x{2327}\x{2328}\x{232B}' .
'\x{232C}\x{232D}\x{232E}\x{232F}\x{2330}\x{2331}\x{2332}' .
'\x{2333}\x{2334}\x{2335}\x{2336}\x{2337}\x{2338}\x{2339}' .
'\x{233A}\x{233B}\x{233C}\x{233D}\x{233E}\x{233F}\x{2340}' .
'\x{2341}\x{2342}\x{2343}\x{2344}\x{2345}\x{2346}\x{2347}' .
'\x{2348}\x{2349}\x{234A}\x{234B}\x{234C}\x{234D}\x{234E}' .
'\x{234F}\x{2350}\x{2351}\x{2352}\x{2353}\x{2354}\x{2355}' .
'\x{2356}\x{2357}\x{2358}\x{2359}\x{235A}\x{235B}\x{235C}' .
'\x{235D}\x{235E}\x{235F}\x{2360}\x{2361}\x{2362}\x{2363}' .
'\x{2364}\x{2365}\x{2366}\x{2367}\x{2368}\x{2369}\x{236A}' .
'\x{236B}\x{236C}\x{236D}\x{236E}\x{236F}\x{2370}\x{2371}' .
'\x{2372}\x{2373}\x{2374}\x{2375}\x{2376}\x{2377}\x{2378}' .
'\x{2379}\x{237A}\x{237B}\x{237D}\x{237E}\x{237F}\x{2380}' .
'\x{2381}\x{2382}\x{2383}\x{2384}\x{2385}\x{2386}\x{2387}' .
'\x{2388}\x{2389}\x{238A}\x{238B}\x{238C}\x{238D}\x{238E}' .
'\x{238F}\x{2390}\x{2391}\x{2392}\x{2393}\x{2394}\x{2395}' .
'\x{2396}\x{2397}\x{2398}\x{2399}\x{239A}\x{23B4}\x{23B5}' .
'\x{23B6}\x{23B7}\x{23B8}\x{23B9}\x{23BA}\x{23BB}\x{23BC}' .
'\x{23BD}\x{23BE}\x{23BF}\x{23C0}\x{23C1}\x{23C2}\x{23C3}' .
'\x{23C4}\x{23C5}\x{23C6}\x{23C7}\x{23C8}\x{23C9}\x{23CA}' .
'\x{23CB}\x{23CC}\x{23CD}\x{23CE}\x{23CF}\x{23D0}\x{23D1}' .
'\x{23D2}\x{23D3}\x{23D4}\x{23D5}\x{23D6}\x{23D7}\x{23D8}' .
'\x{23D9}\x{23DA}\x{23DB}\x{23E2}\x{23E3}\x{23E4}\x{23E5}' .
'\x{23E6}\x{23E7}\x{23E8}\x{23E9}\x{23EA}\x{23EB}\x{23EC}' .
'\x{23ED}\x{23EE}\x{23EF}\x{23F0}\x{23F1}\x{23F2}\x{23F3}' .
'\x{2400}\x{2401}\x{2402}\x{2403}\x{2404}\x{2405}\x{2406}' .
'\x{2407}\x{2408}\x{2409}\x{240A}\x{240B}\x{240C}\x{240D}' .
'\x{240E}\x{240F}\x{2410}\x{2411}\x{2412}\x{2413}\x{2414}' .
'\x{2415}\x{2416}\x{2417}\x{2418}\x{2419}\x{241A}\x{241B}' .
'\x{241C}\x{241D}\x{241E}\x{241F}\x{2420}\x{2421}\x{2422}' .
'\x{2423}\x{2424}\x{2425}\x{2426}\x{2440}\x{2441}\x{2442}' .
'\x{2443}\x{2444}\x{2445}\x{2446}\x{2447}\x{2448}\x{2449}' .
'\x{244A}\x{249C}\x{249D}\x{249E}\x{249F}\x{24A0}\x{24A1}' .
'\x{24A2}\x{24A3}\x{24A4}\x{24A5}\x{24A6}\x{24A7}\x{24A8}' .
'\x{24A9}\x{24AA}\x{24AB}\x{24AC}\x{24AD}\x{24AE}\x{24AF}' .
'\x{24B0}\x{24B1}\x{24B2}\x{24B3}\x{24B4}\x{24B5}\x{24B6}' .
'\x{24B7}\x{24B8}\x{24B9}\x{24BA}\x{24BB}\x{24BC}\x{24BD}' .
'\x{24BE}\x{24BF}\x{24C0}\x{24C1}\x{24C2}\x{24C3}\x{24C4}' .
'\x{24C5}\x{24C6}\x{24C7}\x{24C8}\x{24C9}\x{24CA}\x{24CB}' .
'\x{24CC}\x{24CD}\x{24CE}\x{24CF}\x{24D0}\x{24D1}\x{24D2}' .
'\x{24D3}\x{24D4}\x{24D5}\x{24D6}\x{24D7}\x{24D8}\x{24D9}' .
'\x{24DA}\x{24DB}\x{24DC}\x{24DD}\x{24DE}\x{24DF}\x{24E0}' .
'\x{24E1}\x{24E2}\x{24E3}\x{24E4}\x{24E5}\x{24E6}\x{24E7}' .
'\x{24E8}\x{24E9}\x{2500}\x{2501}\x{2502}\x{2503}\x{2504}' .
'\x{2505}\x{2506}\x{2507}\x{2508}\x{2509}\x{250A}\x{250B}' .
'\x{250C}\x{250D}\x{250E}\x{250F}\x{2510}\x{2511}\x{2512}' .
'\x{2513}\x{2514}\x{2515}\x{2516}\x{2517}\x{2518}\x{2519}' .
'\x{251A}\x{251B}\x{251C}\x{251D}\x{251E}\x{251F}\x{2520}' .
'\x{2521}\x{2522}\x{2523}\x{2524}\x{2525}\x{2526}\x{2527}' .
'\x{2528}\x{2529}\x{252A}\x{252B}\x{252C}\x{252D}\x{252E}' .
'\x{252F}\x{2530}\x{2531}\x{2532}\x{2533}\x{2534}\x{2535}' .
'\x{2536}\x{2537}\x{2538}\x{2539}\x{253A}\x{253B}\x{253C}' .
'\x{253D}\x{253E}\x{253F}\x{2540}\x{2541}\x{2542}\x{2543}' .
'\x{2544}\x{2545}\x{2546}\x{2547}\x{2548}\x{2549}\x{254A}' .
'\x{254B}\x{254C}\x{254D}\x{254E}\x{254F}\x{2550}\x{2551}' .
'\x{2552}\x{2553}\x{2554}\x{2555}\x{2556}\x{2557}\x{2558}' .
'\x{2559}\x{255A}\x{255B}\x{255C}\x{255D}\x{255E}\x{255F}' .
'\x{2560}\x{2561}\x{2562}\x{2563}\x{2564}\x{2565}\x{2566}' .
'\x{2567}\x{2568}\x{2569}\x{256A}\x{256B}\x{256C}\x{256D}' .
'\x{256E}\x{256F}\x{2570}\x{2571}\x{2572}\x{2573}\x{2574}' .
'\x{2575}\x{2576}\x{2577}\x{2578}\x{2579}\x{257A}\x{257B}' .
'\x{257C}\x{257D}\x{257E}\x{257F}\x{2580}\x{2581}\x{2582}' .
'\x{2583}\x{2584}\x{2585}\x{2586}\x{2587}\x{2588}\x{2589}' .
'\x{258A}\x{258B}\x{258C}\x{258D}\x{258E}\x{258F}\x{2590}' .
'\x{2591}\x{2592}\x{2593}\x{2594}\x{2595}\x{2596}\x{2597}' .
'\x{2598}\x{2599}\x{259A}\x{259B}\x{259C}\x{259D}\x{259E}' .
'\x{259F}\x{25A0}\x{25A1}\x{25A2}\x{25A3}\x{25A4}\x{25A5}' .
'\x{25A6}\x{25A7}\x{25A8}\x{25A9}\x{25AA}\x{25AB}\x{25AC}' .
'\x{25AD}\x{25AE}\x{25AF}\x{25B0}\x{25B1}\x{25B2}\x{25B3}' .
'\x{25B4}\x{25B5}\x{25B6}\x{25B8}\x{25B9}\x{25BA}\x{25BB}' .
'\x{25BC}\x{25BD}\x{25BE}\x{25BF}\x{25C0}\x{25C2}\x{25C3}' .
'\x{25C4}\x{25C5}\x{25C6}\x{25C7}\x{25C8}\x{25C9}\x{25CA}' .
'\x{25CB}\x{25CC}\x{25CD}\x{25CE}\x{25CF}\x{25D0}\x{25D1}' .
'\x{25D2}\x{25D3}\x{25D4}\x{25D5}\x{25D6}\x{25D7}\x{25D8}' .
'\x{25D9}\x{25DA}\x{25DB}\x{25DC}\x{25DD}\x{25DE}\x{25DF}' .
'\x{25E0}\x{25E1}\x{25E2}\x{25E3}\x{25E4}\x{25E5}\x{25E6}' .
'\x{25E7}\x{25E8}\x{25E9}\x{25EA}\x{25EB}\x{25EC}\x{25ED}' .
'\x{25EE}\x{25EF}\x{25F0}\x{25F1}\x{25F2}\x{25F3}\x{25F4}' .
'\x{25F5}\x{25F6}\x{25F7}\x{2600}\x{2601}\x{2602}\x{2603}' .
'\x{2604}\x{2605}\x{2606}\x{2607}\x{2608}\x{2609}\x{260A}' .
'\x{260B}\x{260C}\x{260D}\x{260E}\x{260F}\x{2610}\x{2611}' .
'\x{2612}\x{2613}\x{2614}\x{2615}\x{2616}\x{2617}\x{2618}' .
'\x{2619}\x{261A}\x{261B}\x{261C}\x{261D}\x{261E}\x{261F}' .
'\x{2620}\x{2621}\x{2622}\x{2623}\x{2624}\x{2625}\x{2626}' .
'\x{2627}\x{2628}\x{2629}\x{262A}\x{262B}\x{262C}\x{262D}' .
'\x{262E}\x{262F}\x{2630}\x{2631}\x{2632}\x{2633}\x{2634}' .
'\x{2635}\x{2636}\x{2637}\x{2638}\x{2639}\x{263A}\x{263B}' .
'\x{263C}\x{263D}\x{263E}\x{263F}\x{2640}\x{2641}\x{2642}' .
'\x{2643}\x{2644}\x{2645}\x{2646}\x{2647}\x{2648}\x{2649}' .
'\x{264A}\x{264B}\x{264C}\x{264D}\x{264E}\x{264F}\x{2650}' .
'\x{2651}\x{2652}\x{2653}\x{2654}\x{2655}\x{2656}\x{2657}' .
'\x{2658}\x{2659}\x{265A}\x{265B}\x{265C}\x{265D}\x{265E}' .
'\x{265F}\x{2660}\x{2661}\x{2662}\x{2663}\x{2664}\x{2665}' .
'\x{2666}\x{2667}\x{2668}\x{2669}\x{266A}\x{266B}\x{266C}' .
'\x{266D}\x{266E}\x{2670}\x{2671}\x{2672}\x{2673}\x{2674}' .
'\x{2675}\x{2676}\x{2677}\x{2678}\x{2679}\x{267A}\x{267B}' .
'\x{267C}\x{267D}\x{267E}\x{267F}\x{2680}\x{2681}\x{2682}' .
'\x{2683}\x{2684}\x{2685}\x{2686}\x{2687}\x{2688}\x{2689}' .
'\x{268A}\x{268B}\x{268C}\x{268D}\x{268E}\x{268F}\x{2690}' .
'\x{2691}\x{2692}\x{2693}\x{2694}\x{2695}\x{2696}\x{2697}' .
'\x{2698}\x{2699}\x{269A}\x{269B}\x{269C}\x{269D}\x{269E}' .
'\x{269F}\x{26A0}\x{26A1}\x{26A2}\x{26A3}\x{26A4}\x{26A5}' .
'\x{26A6}\x{26A7}\x{26A8}\x{26A9}\x{26AA}\x{26AB}\x{26AC}' .
'\x{26AD}\x{26AE}\x{26AF}\x{26B0}\x{26B1}\x{26B2}\x{26B3}' .
'\x{26B4}\x{26B5}\x{26B6}\x{26B7}\x{26B8}\x{26B9}\x{26BA}' .
'\x{26BB}\x{26BC}\x{26BD}\x{26BE}\x{26BF}\x{26C0}\x{26C1}' .
'\x{26C2}\x{26C3}\x{26C4}\x{26C5}\x{26C6}\x{26C7}\x{26C8}' .
'\x{26C9}\x{26CA}\x{26CB}\x{26CC}\x{26CD}\x{26CE}\x{26CF}' .
'\x{26D0}\x{26D1}\x{26D2}\x{26D3}\x{26D4}\x{26D5}\x{26D6}' .
'\x{26D7}\x{26D8}\x{26D9}\x{26DA}\x{26DB}\x{26DC}\x{26DD}' .
'\x{26DE}\x{26DF}\x{26E0}\x{26E1}\x{26E2}\x{26E3}\x{26E4}' .
'\x{26E5}\x{26E6}\x{26E7}\x{26E8}\x{26E9}\x{26EA}\x{26EB}' .
'\x{26EC}\x{26ED}\x{26EE}\x{26EF}\x{26F0}\x{26F1}\x{26F2}' .
'\x{26F3}\x{26F4}\x{26F5}\x{26F6}\x{26F7}\x{26F8}\x{26F9}' .
'\x{26FA}\x{26FB}\x{26FC}\x{26FD}\x{26FE}\x{26FF}\x{2701}' .
'\x{2702}\x{2703}\x{2704}\x{2705}\x{2706}\x{2707}\x{2708}' .
'\x{2709}\x{270A}\x{270B}\x{270C}\x{270D}\x{270E}\x{270F}' .
'\x{2710}\x{2711}\x{2712}\x{2713}\x{2714}\x{2715}\x{2716}' .
'\x{2717}\x{2718}\x{2719}\x{271A}\x{271B}\x{271C}\x{271D}' .
'\x{271E}\x{271F}\x{2720}\x{2721}\x{2722}\x{2723}\x{2724}' .
'\x{2725}\x{2726}\x{2727}\x{2728}\x{2729}\x{272A}\x{272B}' .
'\x{272C}\x{272D}\x{272E}\x{272F}\x{2730}\x{2731}\x{2732}' .
'\x{2733}\x{2734}\x{2735}\x{2736}\x{2737}\x{2738}\x{2739}' .
'\x{273A}\x{273B}\x{273C}\x{273D}\x{273E}\x{273F}\x{2740}' .
'\x{2741}\x{2742}\x{2743}\x{2744}\x{2745}\x{2746}\x{2747}' .
'\x{2748}\x{2749}\x{274A}\x{274B}\x{274C}\x{274D}\x{274E}' .
'\x{274F}\x{2750}\x{2751}\x{2752}\x{2753}\x{2754}\x{2755}' .
'\x{2756}\x{2757}\x{2758}\x{2759}\x{275A}\x{275B}\x{275C}' .
'\x{275D}\x{275E}\x{275F}\x{2760}\x{2761}\x{2762}\x{2763}' .
'\x{2764}\x{2765}\x{2766}\x{2767}\x{2794}\x{2795}\x{2796}' .
'\x{2797}\x{2798}\x{2799}\x{279A}\x{279B}\x{279C}\x{279D}' .
'\x{279E}\x{279F}\x{27A0}\x{27A1}\x{27A2}\x{27A3}\x{27A4}' .
'\x{27A5}\x{27A6}\x{27A7}\x{27A8}\x{27A9}\x{27AA}\x{27AB}' .
'\x{27AC}\x{27AD}\x{27AE}\x{27AF}\x{27B0}\x{27B1}\x{27B2}' .
'\x{27B3}\x{27B4}\x{27B5}\x{27B6}\x{27B7}\x{27B8}\x{27B9}' .
'\x{27BA}\x{27BB}\x{27BC}\x{27BD}\x{27BE}\x{27BF}\x{2800}' .
'\x{2801}\x{2802}\x{2803}\x{2804}\x{2805}\x{2806}\x{2807}' .
'\x{2808}\x{2809}\x{280A}\x{280B}\x{280C}\x{280D}\x{280E}' .
'\x{280F}\x{2810}\x{2811}\x{2812}\x{2813}\x{2814}\x{2815}' .
'\x{2816}\x{2817}\x{2818}\x{2819}\x{281A}\x{281B}\x{281C}' .
'\x{281D}\x{281E}\x{281F}\x{2820}\x{2821}\x{2822}\x{2823}' .
'\x{2824}\x{2825}\x{2826}\x{2827}\x{2828}\x{2829}\x{282A}' .
'\x{282B}\x{282C}\x{282D}\x{282E}\x{282F}\x{2830}\x{2831}' .
'\x{2832}\x{2833}\x{2834}\x{2835}\x{2836}\x{2837}\x{2838}' .
'\x{2839}\x{283A}\x{283B}\x{283C}\x{283D}\x{283E}\x{283F}' .
'\x{2840}\x{2841}\x{2842}\x{2843}\x{2844}\x{2845}\x{2846}' .
'\x{2847}\x{2848}\x{2849}\x{284A}\x{284B}\x{284C}\x{284D}' .
'\x{284E}\x{284F}\x{2850}\x{2851}\x{2852}\x{2853}\x{2854}' .
'\x{2855}\x{2856}\x{2857}\x{2858}\x{2859}\x{285A}\x{285B}' .
'\x{285C}\x{285D}\x{285E}\x{285F}\x{2860}\x{2861}\x{2862}' .
'\x{2863}\x{2864}\x{2865}\x{2866}\x{2867}\x{2868}\x{2869}' .
'\x{286A}\x{286B}\x{286C}\x{286D}\x{286E}\x{286F}\x{2870}' .
'\x{2871}\x{2872}\x{2873}\x{2874}\x{2875}\x{2876}\x{2877}' .
'\x{2878}\x{2879}\x{287A}\x{287B}\x{287C}\x{287D}\x{287E}' .
'\x{287F}\x{2880}\x{2881}\x{2882}\x{2883}\x{2884}\x{2885}' .
'\x{2886}\x{2887}\x{2888}\x{2889}\x{288A}\x{288B}\x{288C}' .
'\x{288D}\x{288E}\x{288F}\x{2890}\x{2891}\x{2892}\x{2893}' .
'\x{2894}\x{2895}\x{2896}\x{2897}\x{2898}\x{2899}\x{289A}' .
'\x{289B}\x{289C}\x{289D}\x{289E}\x{289F}\x{28A0}\x{28A1}' .
'\x{28A2}\x{28A3}\x{28A4}\x{28A5}\x{28A6}\x{28A7}\x{28A8}' .
'\x{28A9}\x{28AA}\x{28AB}\x{28AC}\x{28AD}\x{28AE}\x{28AF}' .
'\x{28B0}\x{28B1}\x{28B2}\x{28B3}\x{28B4}\x{28B5}\x{28B6}' .
'\x{28B7}\x{28B8}\x{28B9}\x{28BA}\x{28BB}\x{28BC}\x{28BD}' .
'\x{28BE}\x{28BF}\x{28C0}\x{28C1}\x{28C2}\x{28C3}\x{28C4}' .
'\x{28C5}\x{28C6}\x{28C7}\x{28C8}\x{28C9}\x{28CA}\x{28CB}' .
'\x{28CC}\x{28CD}\x{28CE}\x{28CF}\x{28D0}\x{28D1}\x{28D2}' .
'\x{28D3}\x{28D4}\x{28D5}\x{28D6}\x{28D7}\x{28D8}\x{28D9}' .
'\x{28DA}\x{28DB}\x{28DC}\x{28DD}\x{28DE}\x{28DF}\x{28E0}' .
'\x{28E1}\x{28E2}\x{28E3}\x{28E4}\x{28E5}\x{28E6}\x{28E7}' .
'\x{28E8}\x{28E9}\x{28EA}\x{28EB}\x{28EC}\x{28ED}\x{28EE}' .
'\x{28EF}\x{28F0}\x{28F1}\x{28F2}\x{28F3}\x{28F4}\x{28F5}' .
'\x{28F6}\x{28F7}\x{28F8}\x{28F9}\x{28FA}\x{28FB}\x{28FC}' .
'\x{28FD}\x{28FE}\x{28FF}\x{2B00}\x{2B01}\x{2B02}\x{2B03}' .
'\x{2B04}\x{2B05}\x{2B06}\x{2B07}\x{2B08}\x{2B09}\x{2B0A}' .
'\x{2B0B}\x{2B0C}\x{2B0D}\x{2B0E}\x{2B0F}\x{2B10}\x{2B11}' .
'\x{2B12}\x{2B13}\x{2B14}\x{2B15}\x{2B16}\x{2B17}\x{2B18}' .
'\x{2B19}\x{2B1A}\x{2B1B}\x{2B1C}\x{2B1D}\x{2B1E}\x{2B1F}' .
'\x{2B20}\x{2B21}\x{2B22}\x{2B23}\x{2B24}\x{2B25}\x{2B26}' .
'\x{2B27}\x{2B28}\x{2B29}\x{2B2A}\x{2B2B}\x{2B2C}\x{2B2D}' .
'\x{2B2E}\x{2B2F}\x{2B45}\x{2B46}\x{2B50}\x{2B51}\x{2B52}' .
'\x{2B53}\x{2B54}\x{2B55}\x{2B56}\x{2B57}\x{2B58}\x{2B59}' .
'\x{2CE5}\x{2CE6}\x{2CE7}\x{2CE8}\x{2CE9}\x{2CEA}\x{2E80}' .
'\x{2E81}\x{2E82}\x{2E83}\x{2E84}\x{2E85}\x{2E86}\x{2E87}' .
'\x{2E88}\x{2E89}\x{2E8A}\x{2E8B}\x{2E8C}\x{2E8D}\x{2E8E}' .
'\x{2E8F}\x{2E90}\x{2E91}\x{2E92}\x{2E93}\x{2E94}\x{2E95}' .
'\x{2E96}\x{2E97}\x{2E98}\x{2E99}\x{2E9B}\x{2E9C}\x{2E9D}' .
'\x{2E9E}\x{2E9F}\x{2EA0}\x{2EA1}\x{2EA2}\x{2EA3}\x{2EA4}' .
'\x{2EA5}\x{2EA6}\x{2EA7}\x{2EA8}\x{2EA9}\x{2EAA}\x{2EAB}' .
'\x{2EAC}\x{2EAD}\x{2EAE}\x{2EAF}\x{2EB0}\x{2EB1}\x{2EB2}' .
'\x{2EB3}\x{2EB4}\x{2EB5}\x{2EB6}\x{2EB7}\x{2EB8}\x{2EB9}' .
'\x{2EBA}\x{2EBB}\x{2EBC}\x{2EBD}\x{2EBE}\x{2EBF}\x{2EC0}' .
'\x{2EC1}\x{2EC2}\x{2EC3}\x{2EC4}\x{2EC5}\x{2EC6}\x{2EC7}' .
'\x{2EC8}\x{2EC9}\x{2ECA}\x{2ECB}\x{2ECC}\x{2ECD}\x{2ECE}' .
'\x{2ECF}\x{2ED0}\x{2ED1}\x{2ED2}\x{2ED3}\x{2ED4}\x{2ED5}' .
'\x{2ED6}\x{2ED7}\x{2ED8}\x{2ED9}\x{2EDA}\x{2EDB}\x{2EDC}' .
'\x{2EDD}\x{2EDE}\x{2EDF}\x{2EE0}\x{2EE1}\x{2EE2}\x{2EE3}' .
'\x{2EE4}\x{2EE5}\x{2EE6}\x{2EE7}\x{2EE8}\x{2EE9}\x{2EEA}' .
'\x{2EEB}\x{2EEC}\x{2EED}\x{2EEE}\x{2EEF}\x{2EF0}\x{2EF1}' .
'\x{2EF2}\x{2EF3}\x{2F00}\x{2F01}\x{2F02}\x{2F03}\x{2F04}' .
'\x{2F05}\x{2F06}\x{2F07}\x{2F08}\x{2F09}\x{2F0A}\x{2F0B}' .
'\x{2F0C}\x{2F0D}\x{2F0E}\x{2F0F}\x{2F10}\x{2F11}\x{2F12}' .
'\x{2F13}\x{2F14}\x{2F15}\x{2F16}\x{2F17}\x{2F18}\x{2F19}' .
'\x{2F1A}\x{2F1B}\x{2F1C}\x{2F1D}\x{2F1E}\x{2F1F}\x{2F20}' .
'\x{2F21}\x{2F22}\x{2F23}\x{2F24}\x{2F25}\x{2F26}\x{2F27}' .
'\x{2F28}\x{2F29}\x{2F2A}\x{2F2B}\x{2F2C}\x{2F2D}\x{2F2E}' .
'\x{2F2F}\x{2F30}\x{2F31}\x{2F32}\x{2F33}\x{2F34}\x{2F35}' .
'\x{2F36}\x{2F37}\x{2F38}\x{2F39}\x{2F3A}\x{2F3B}\x{2F3C}' .
'\x{2F3D}\x{2F3E}\x{2F3F}\x{2F40}\x{2F41}\x{2F42}\x{2F43}' .
'\x{2F44}\x{2F45}\x{2F46}\x{2F47}\x{2F48}\x{2F49}\x{2F4A}' .
'\x{2F4B}\x{2F4C}\x{2F4D}\x{2F4E}\x{2F4F}\x{2F50}\x{2F51}' .
'\x{2F52}\x{2F53}\x{2F54}\x{2F55}\x{2F56}\x{2F57}\x{2F58}' .
'\x{2F59}\x{2F5A}\x{2F5B}\x{2F5C}\x{2F5D}\x{2F5E}\x{2F5F}' .
'\x{2F60}\x{2F61}\x{2F62}\x{2F63}\x{2F64}\x{2F65}\x{2F66}' .
'\x{2F67}\x{2F68}\x{2F69}\x{2F6A}\x{2F6B}\x{2F6C}\x{2F6D}' .
'\x{2F6E}\x{2F6F}\x{2F70}\x{2F71}\x{2F72}\x{2F73}\x{2F74}' .
'\x{2F75}\x{2F76}\x{2F77}\x{2F78}\x{2F79}\x{2F7A}\x{2F7B}' .
'\x{2F7C}\x{2F7D}\x{2F7E}\x{2F7F}\x{2F80}\x{2F81}\x{2F82}' .
'\x{2F83}\x{2F84}\x{2F85}\x{2F86}\x{2F87}\x{2F88}\x{2F89}' .
'\x{2F8A}\x{2F8B}\x{2F8C}\x{2F8D}\x{2F8E}\x{2F8F}\x{2F90}' .
'\x{2F91}\x{2F92}\x{2F93}\x{2F94}\x{2F95}\x{2F96}\x{2F97}' .
'\x{2F98}\x{2F99}\x{2F9A}\x{2F9B}\x{2F9C}\x{2F9D}\x{2F9E}' .
'\x{2F9F}\x{2FA0}\x{2FA1}\x{2FA2}\x{2FA3}\x{2FA4}\x{2FA5}' .
'\x{2FA6}\x{2FA7}\x{2FA8}\x{2FA9}\x{2FAA}\x{2FAB}\x{2FAC}' .
'\x{2FAD}\x{2FAE}\x{2FAF}\x{2FB0}\x{2FB1}\x{2FB2}\x{2FB3}' .
'\x{2FB4}\x{2FB5}\x{2FB6}\x{2FB7}\x{2FB8}\x{2FB9}\x{2FBA}' .
'\x{2FBB}\x{2FBC}\x{2FBD}\x{2FBE}\x{2FBF}\x{2FC0}\x{2FC1}' .
'\x{2FC2}\x{2FC3}\x{2FC4}\x{2FC5}\x{2FC6}\x{2FC7}\x{2FC8}' .
'\x{2FC9}\x{2FCA}\x{2FCB}\x{2FCC}\x{2FCD}\x{2FCE}\x{2FCF}' .
'\x{2FD0}\x{2FD1}\x{2FD2}\x{2FD3}\x{2FD4}\x{2FD5}\x{2FF0}' .
'\x{2FF1}\x{2FF2}\x{2FF3}\x{2FF4}\x{2FF5}\x{2FF6}\x{2FF7}' .
'\x{2FF8}\x{2FF9}\x{2FFA}\x{2FFB}\x{3004}\x{3012}\x{3013}' .
'\x{3020}\x{3036}\x{3037}\x{303E}\x{303F}\x{3190}\x{3191}' .
'\x{3196}\x{3197}\x{3198}\x{3199}\x{319A}\x{319B}\x{319C}' .
'\x{319D}\x{319E}\x{319F}\x{31C0}\x{31C1}\x{31C2}\x{31C3}' .
'\x{31C4}\x{31C5}\x{31C6}\x{31C7}\x{31C8}\x{31C9}\x{31CA}' .
'\x{31CB}\x{31CC}\x{31CD}\x{31CE}\x{31CF}\x{31D0}\x{31D1}' .
'\x{31D2}\x{31D3}\x{31D4}\x{31D5}\x{31D6}\x{31D7}\x{31D8}' .
'\x{31D9}\x{31DA}\x{31DB}\x{31DC}\x{31DD}\x{31DE}\x{31DF}' .
'\x{31E0}\x{31E1}\x{31E2}\x{31E3}\x{3200}\x{3201}\x{3202}' .
'\x{3203}\x{3204}\x{3205}\x{3206}\x{3207}\x{3208}\x{3209}' .
'\x{320A}\x{320B}\x{320C}\x{320D}\x{320E}\x{320F}\x{3210}' .
'\x{3211}\x{3212}\x{3213}\x{3214}\x{3215}\x{3216}\x{3217}' .
'\x{3218}\x{3219}\x{321A}\x{321B}\x{321C}\x{321D}\x{321E}' .
'\x{322A}\x{322B}\x{322C}\x{322D}\x{322E}\x{322F}\x{3230}' .
'\x{3231}\x{3232}\x{3233}\x{3234}\x{3235}\x{3236}\x{3237}' .
'\x{3238}\x{3239}\x{323A}\x{323B}\x{323C}\x{323D}\x{323E}' .
'\x{323F}\x{3240}\x{3241}\x{3242}\x{3243}\x{3244}\x{3245}' .
'\x{3246}\x{3247}\x{3250}\x{3260}\x{3261}\x{3262}\x{3263}' .
'\x{3264}\x{3265}\x{3266}\x{3267}\x{3268}\x{3269}\x{326A}' .
'\x{326B}\x{326C}\x{326D}\x{326E}\x{326F}\x{3270}\x{3271}' .
'\x{3272}\x{3273}\x{3274}\x{3275}\x{3276}\x{3277}\x{3278}' .
'\x{3279}\x{327A}\x{327B}\x{327C}\x{327D}\x{327E}\x{327F}' .
'\x{328A}\x{328B}\x{328C}\x{328D}\x{328E}\x{328F}\x{3290}' .
'\x{3291}\x{3292}\x{3293}\x{3294}\x{3295}\x{3296}\x{3297}' .
'\x{3298}\x{3299}\x{329A}\x{329B}\x{329C}\x{329D}\x{329E}' .
'\x{329F}\x{32A0}\x{32A1}\x{32A2}\x{32A3}\x{32A4}\x{32A5}' .
'\x{32A6}\x{32A7}\x{32A8}\x{32A9}\x{32AA}\x{32AB}\x{32AC}' .
'\x{32AD}\x{32AE}\x{32AF}\x{32B0}\x{32C0}\x{32C1}\x{32C2}' .
'\x{32C3}\x{32C4}\x{32C5}\x{32C6}\x{32C7}\x{32C8}\x{32C9}' .
'\x{32CA}\x{32CB}\x{32CC}\x{32CD}\x{32CE}\x{32CF}\x{32D0}' .
'\x{32D1}\x{32D2}\x{32D3}\x{32D4}\x{32D5}\x{32D6}\x{32D7}' .
'\x{32D8}\x{32D9}\x{32DA}\x{32DB}\x{32DC}\x{32DD}\x{32DE}' .
'\x{32DF}\x{32E0}\x{32E1}\x{32E2}\x{32E3}\x{32E4}\x{32E5}' .
'\x{32E6}\x{32E7}\x{32E8}\x{32E9}\x{32EA}\x{32EB}\x{32EC}' .
'\x{32ED}\x{32EE}\x{32EF}\x{32F0}\x{32F1}\x{32F2}\x{32F3}' .
'\x{32F4}\x{32F5}\x{32F6}\x{32F7}\x{32F8}\x{32F9}\x{32FA}' .
'\x{32FB}\x{32FC}\x{32FD}\x{32FE}\x{3300}\x{3301}\x{3302}' .
'\x{3303}\x{3304}\x{3305}\x{3306}\x{3307}\x{3308}\x{3309}' .
'\x{330A}\x{330B}\x{330C}\x{330D}\x{330E}\x{330F}\x{3310}' .
'\x{3311}\x{3312}\x{3313}\x{3314}\x{3315}\x{3316}\x{3317}' .
'\x{3318}\x{3319}\x{331A}\x{331B}\x{331C}\x{331D}\x{331E}' .
'\x{331F}\x{3320}\x{3321}\x{3322}\x{3323}\x{3324}\x{3325}' .
'\x{3326}\x{3327}\x{3328}\x{3329}\x{332A}\x{332B}\x{332C}' .
'\x{332D}\x{332E}\x{332F}\x{3330}\x{3331}\x{3332}\x{3333}' .
'\x{3334}\x{3335}\x{3336}\x{3337}\x{3338}\x{3339}\x{333A}' .
'\x{333B}\x{333C}\x{333D}\x{333E}\x{333F}\x{3340}\x{3341}' .
'\x{3342}\x{3343}\x{3344}\x{3345}\x{3346}\x{3347}\x{3348}' .
'\x{3349}\x{334A}\x{334B}\x{334C}\x{334D}\x{334E}\x{334F}' .
'\x{3350}\x{3351}\x{3352}\x{3353}\x{3354}\x{3355}\x{3356}' .
'\x{3357}\x{3358}\x{3359}\x{335A}\x{335B}\x{335C}\x{335D}' .
'\x{335E}\x{335F}\x{3360}\x{3361}\x{3362}\x{3363}\x{3364}' .
'\x{3365}\x{3366}\x{3367}\x{3368}\x{3369}\x{336A}\x{336B}' .
'\x{336C}\x{336D}\x{336E}\x{336F}\x{3370}\x{3371}\x{3372}' .
'\x{3373}\x{3374}\x{3375}\x{3376}\x{3377}\x{3378}\x{3379}' .
'\x{337A}\x{337B}\x{337C}\x{337D}\x{337E}\x{337F}\x{3380}' .
'\x{3381}\x{3382}\x{3383}\x{3384}\x{3385}\x{3386}\x{3387}' .
'\x{3388}\x{3389}\x{338A}\x{338B}\x{338C}\x{338D}\x{338E}' .
'\x{338F}\x{3390}\x{3391}\x{3392}\x{3393}\x{3394}\x{3395}' .
'\x{3396}\x{3397}\x{3398}\x{3399}\x{339A}\x{339B}\x{339C}' .
'\x{339D}\x{339E}\x{339F}\x{33A0}\x{33A1}\x{33A2}\x{33A3}' .
'\x{33A4}\x{33A5}\x{33A6}\x{33A7}\x{33A8}\x{33A9}\x{33AA}' .
'\x{33AB}\x{33AC}\x{33AD}\x{33AE}\x{33AF}\x{33B0}\x{33B1}' .
'\x{33B2}\x{33B3}\x{33B4}\x{33B5}\x{33B6}\x{33B7}\x{33B8}' .
'\x{33B9}\x{33BA}\x{33BB}\x{33BC}\x{33BD}\x{33BE}\x{33BF}' .
'\x{33C0}\x{33C1}\x{33C2}\x{33C3}\x{33C4}\x{33C5}\x{33C6}' .
'\x{33C7}\x{33C8}\x{33C9}\x{33CA}\x{33CB}\x{33CC}\x{33CD}' .
'\x{33CE}\x{33CF}\x{33D0}\x{33D1}\x{33D2}\x{33D3}\x{33D4}' .
'\x{33D5}\x{33D6}\x{33D7}\x{33D8}\x{33D9}\x{33DA}\x{33DB}' .
'\x{33DC}\x{33DD}\x{33DE}\x{33DF}\x{33E0}\x{33E1}\x{33E2}' .
'\x{33E3}\x{33E4}\x{33E5}\x{33E6}\x{33E7}\x{33E8}\x{33E9}' .
'\x{33EA}\x{33EB}\x{33EC}\x{33ED}\x{33EE}\x{33EF}\x{33F0}' .
'\x{33F1}\x{33F2}\x{33F3}\x{33F4}\x{33F5}\x{33F6}\x{33F7}' .
'\x{33F8}\x{33F9}\x{33FA}\x{33FB}\x{33FC}\x{33FD}\x{33FE}' .
'\x{33FF}\x{4DC0}\x{4DC1}\x{4DC2}\x{4DC3}\x{4DC4}\x{4DC5}' .
'\x{4DC6}\x{4DC7}\x{4DC8}\x{4DC9}\x{4DCA}\x{4DCB}\x{4DCC}' .
'\x{4DCD}\x{4DCE}\x{4DCF}\x{4DD0}\x{4DD1}\x{4DD2}\x{4DD3}' .
'\x{4DD4}\x{4DD5}\x{4DD6}\x{4DD7}\x{4DD8}\x{4DD9}\x{4DDA}' .
'\x{4DDB}\x{4DDC}\x{4DDD}\x{4DDE}\x{4DDF}\x{4DE0}\x{4DE1}' .
'\x{4DE2}\x{4DE3}\x{4DE4}\x{4DE5}\x{4DE6}\x{4DE7}\x{4DE8}' .
'\x{4DE9}\x{4DEA}\x{4DEB}\x{4DEC}\x{4DED}\x{4DEE}\x{4DEF}' .
'\x{4DF0}\x{4DF1}\x{4DF2}\x{4DF3}\x{4DF4}\x{4DF5}\x{4DF6}' .
'\x{4DF7}\x{4DF8}\x{4DF9}\x{4DFA}\x{4DFB}\x{4DFC}\x{4DFD}' .
'\x{4DFE}\x{4DFF}\x{A490}\x{A491}\x{A492}\x{A493}\x{A494}' .
'\x{A495}\x{A496}\x{A497}\x{A498}\x{A499}\x{A49A}\x{A49B}' .
'\x{A49C}\x{A49D}\x{A49E}\x{A49F}\x{A4A0}\x{A4A1}\x{A4A2}' .
'\x{A4A3}\x{A4A4}\x{A4A5}\x{A4A6}\x{A4A7}\x{A4A8}\x{A4A9}' .
'\x{A4AA}\x{A4AB}\x{A4AC}\x{A4AD}\x{A4AE}\x{A4AF}\x{A4B0}' .
'\x{A4B1}\x{A4B2}\x{A4B3}\x{A4B4}\x{A4B5}\x{A4B6}\x{A4B7}' .
'\x{A4B8}\x{A4B9}\x{A4BA}\x{A4BB}\x{A4BC}\x{A4BD}\x{A4BE}' .
'\x{A4BF}\x{A4C0}\x{A4C1}\x{A4C2}\x{A4C3}\x{A4C4}\x{A4C5}' .
'\x{A4C6}\x{A828}\x{A829}\x{A82A}\x{A82B}\x{A836}\x{A837}' .
'\x{A839}\x{AA77}\x{AA78}\x{AA79}\x{FDFD}\x{FFE4}\x{FFE8}' .
'\x{FFED}\x{FFEE}\x{FFFC}\x{FFFD}\x{10137}\x{10138}\x{10139}' .
'\x{1013A}\x{1013B}\x{1013C}\x{1013D}\x{1013E}\x{1013F}\x{10179}' .
'\x{1017A}\x{1017B}\x{1017C}\x{1017D}\x{1017E}\x{1017F}\x{10180}' .
'\x{10181}\x{10182}\x{10183}\x{10184}\x{10185}\x{10186}\x{10187}' .
'\x{10188}\x{10189}\x{10190}\x{10191}\x{10192}\x{10193}\x{10194}' .
'\x{10195}\x{10196}\x{10197}\x{10198}\x{10199}\x{1019A}\x{1019B}' .
'\x{101D0}\x{101D1}\x{101D2}\x{101D3}\x{101D4}\x{101D5}\x{101D6}' .
'\x{101D7}\x{101D8}\x{101D9}\x{101DA}\x{101DB}\x{101DC}\x{101DD}' .
'\x{101DE}\x{101DF}\x{101E0}\x{101E1}\x{101E2}\x{101E3}\x{101E4}' .
'\x{101E5}\x{101E6}\x{101E7}\x{101E8}\x{101E9}\x{101EA}\x{101EB}' .
'\x{101EC}\x{101ED}\x{101EE}\x{101EF}\x{101F0}\x{101F1}\x{101F2}' .
'\x{101F3}\x{101F4}\x{101F5}\x{101F6}\x{101F7}\x{101F8}\x{101F9}' .
'\x{101FA}\x{101FB}\x{101FC}\x{1D000}\x{1D001}\x{1D002}\x{1D003}' .
'\x{1D004}\x{1D005}\x{1D006}\x{1D007}\x{1D008}\x{1D009}\x{1D00A}' .
'\x{1D00B}\x{1D00C}\x{1D00D}\x{1D00E}\x{1D00F}\x{1D010}\x{1D011}' .
'\x{1D012}\x{1D013}\x{1D014}\x{1D015}\x{1D016}\x{1D017}\x{1D018}' .
'\x{1D019}\x{1D01A}\x{1D01B}\x{1D01C}\x{1D01D}\x{1D01E}\x{1D01F}' .
'\x{1D020}\x{1D021}\x{1D022}\x{1D023}\x{1D024}\x{1D025}\x{1D026}' .
'\x{1D027}\x{1D028}\x{1D029}\x{1D02A}\x{1D02B}\x{1D02C}\x{1D02D}' .
'\x{1D02E}\x{1D02F}\x{1D030}\x{1D031}\x{1D032}\x{1D033}\x{1D034}' .
'\x{1D035}\x{1D036}\x{1D037}\x{1D038}\x{1D039}\x{1D03A}\x{1D03B}' .
'\x{1D03C}\x{1D03D}\x{1D03E}\x{1D03F}\x{1D040}\x{1D041}\x{1D042}' .
'\x{1D043}\x{1D044}\x{1D045}\x{1D046}\x{1D047}\x{1D048}\x{1D049}' .
'\x{1D04A}\x{1D04B}\x{1D04C}\x{1D04D}\x{1D04E}\x{1D04F}\x{1D050}' .
'\x{1D051}\x{1D052}\x{1D053}\x{1D054}\x{1D055}\x{1D056}\x{1D057}' .
'\x{1D058}\x{1D059}\x{1D05A}\x{1D05B}\x{1D05C}\x{1D05D}\x{1D05E}' .
'\x{1D05F}\x{1D060}\x{1D061}\x{1D062}\x{1D063}\x{1D064}\x{1D065}' .
'\x{1D066}\x{1D067}\x{1D068}\x{1D069}\x{1D06A}\x{1D06B}\x{1D06C}' .
'\x{1D06D}\x{1D06E}\x{1D06F}\x{1D070}\x{1D071}\x{1D072}\x{1D073}' .
'\x{1D074}\x{1D075}\x{1D076}\x{1D077}\x{1D078}\x{1D079}\x{1D07A}' .
'\x{1D07B}\x{1D07C}\x{1D07D}\x{1D07E}\x{1D07F}\x{1D080}\x{1D081}' .
'\x{1D082}\x{1D083}\x{1D084}\x{1D085}\x{1D086}\x{1D087}\x{1D088}' .
'\x{1D089}\x{1D08A}\x{1D08B}\x{1D08C}\x{1D08D}\x{1D08E}\x{1D08F}' .
'\x{1D090}\x{1D091}\x{1D092}\x{1D093}\x{1D094}\x{1D095}\x{1D096}' .
'\x{1D097}\x{1D098}\x{1D099}\x{1D09A}\x{1D09B}\x{1D09C}\x{1D09D}' .
'\x{1D09E}\x{1D09F}\x{1D0A0}\x{1D0A1}\x{1D0A2}\x{1D0A3}\x{1D0A4}' .
'\x{1D0A5}\x{1D0A6}\x{1D0A7}\x{1D0A8}\x{1D0A9}\x{1D0AA}\x{1D0AB}' .
'\x{1D0AC}\x{1D0AD}\x{1D0AE}\x{1D0AF}\x{1D0B0}\x{1D0B1}\x{1D0B2}' .
'\x{1D0B3}\x{1D0B4}\x{1D0B5}\x{1D0B6}\x{1D0B7}\x{1D0B8}\x{1D0B9}' .
'\x{1D0BA}\x{1D0BB}\x{1D0BC}\x{1D0BD}\x{1D0BE}\x{1D0BF}\x{1D0C0}' .
'\x{1D0C1}\x{1D0C2}\x{1D0C3}\x{1D0C4}\x{1D0C5}\x{1D0C6}\x{1D0C7}' .
'\x{1D0C8}\x{1D0C9}\x{1D0CA}\x{1D0CB}\x{1D0CC}\x{1D0CD}\x{1D0CE}' .
'\x{1D0CF}\x{1D0D0}\x{1D0D1}\x{1D0D2}\x{1D0D3}\x{1D0D4}\x{1D0D5}' .
'\x{1D0D6}\x{1D0D7}\x{1D0D8}\x{1D0D9}\x{1D0DA}\x{1D0DB}\x{1D0DC}' .
'\x{1D0DD}\x{1D0DE}\x{1D0DF}\x{1D0E0}\x{1D0E1}\x{1D0E2}\x{1D0E3}' .
'\x{1D0E4}\x{1D0E5}\x{1D0E6}\x{1D0E7}\x{1D0E8}\x{1D0E9}\x{1D0EA}' .
'\x{1D0EB}\x{1D0EC}\x{1D0ED}\x{1D0EE}\x{1D0EF}\x{1D0F0}\x{1D0F1}' .
'\x{1D0F2}\x{1D0F3}\x{1D0F4}\x{1D0F5}\x{1D100}\x{1D101}\x{1D102}' .
'\x{1D103}\x{1D104}\x{1D105}\x{1D106}\x{1D107}\x{1D108}\x{1D109}' .
'\x{1D10A}\x{1D10B}\x{1D10C}\x{1D10D}\x{1D10E}\x{1D10F}\x{1D110}' .
'\x{1D111}\x{1D112}\x{1D113}\x{1D114}\x{1D115}\x{1D116}\x{1D117}' .
'\x{1D118}\x{1D119}\x{1D11A}\x{1D11B}\x{1D11C}\x{1D11D}\x{1D11E}' .
'\x{1D11F}\x{1D120}\x{1D121}\x{1D122}\x{1D123}\x{1D124}\x{1D125}' .
'\x{1D126}\x{1D129}\x{1D12A}\x{1D12B}\x{1D12C}\x{1D12D}\x{1D12E}' .
'\x{1D12F}\x{1D130}\x{1D131}\x{1D132}\x{1D133}\x{1D134}\x{1D135}' .
'\x{1D136}\x{1D137}\x{1D138}\x{1D139}\x{1D13A}\x{1D13B}\x{1D13C}' .
'\x{1D13D}\x{1D13E}\x{1D13F}\x{1D140}\x{1D141}\x{1D142}\x{1D143}' .
'\x{1D144}\x{1D145}\x{1D146}\x{1D147}\x{1D148}\x{1D149}\x{1D14A}' .
'\x{1D14B}\x{1D14C}\x{1D14D}\x{1D14E}\x{1D14F}\x{1D150}\x{1D151}' .
'\x{1D152}\x{1D153}\x{1D154}\x{1D155}\x{1D156}\x{1D157}\x{1D158}' .
'\x{1D159}\x{1D15A}\x{1D15B}\x{1D15C}\x{1D15D}\x{1D15E}\x{1D15F}' .
'\x{1D160}\x{1D161}\x{1D162}\x{1D163}\x{1D164}\x{1D16A}\x{1D16B}' .
'\x{1D16C}\x{1D183}\x{1D184}\x{1D18C}\x{1D18D}\x{1D18E}\x{1D18F}' .
'\x{1D190}\x{1D191}\x{1D192}\x{1D193}\x{1D194}\x{1D195}\x{1D196}' .
'\x{1D197}\x{1D198}\x{1D199}\x{1D19A}\x{1D19B}\x{1D19C}\x{1D19D}' .
'\x{1D19E}\x{1D19F}\x{1D1A0}\x{1D1A1}\x{1D1A2}\x{1D1A3}\x{1D1A4}' .
'\x{1D1A5}\x{1D1A6}\x{1D1A7}\x{1D1A8}\x{1D1A9}\x{1D1AE}\x{1D1AF}' .
'\x{1D1B0}\x{1D1B1}\x{1D1B2}\x{1D1B3}\x{1D1B4}\x{1D1B5}\x{1D1B6}' .
'\x{1D1B7}\x{1D1B8}\x{1D1B9}\x{1D1BA}\x{1D1BB}\x{1D1BC}\x{1D1BD}' .
'\x{1D1BE}\x{1D1BF}\x{1D1C0}\x{1D1C1}\x{1D1C2}\x{1D1C3}\x{1D1C4}' .
'\x{1D1C5}\x{1D1C6}\x{1D1C7}\x{1D1C8}\x{1D1C9}\x{1D1CA}\x{1D1CB}' .
'\x{1D1CC}\x{1D1CD}\x{1D1CE}\x{1D1CF}\x{1D1D0}\x{1D1D1}\x{1D1D2}' .
'\x{1D1D3}\x{1D1D4}\x{1D1D5}\x{1D1D6}\x{1D1D7}\x{1D1D8}\x{1D1D9}' .
'\x{1D1DA}\x{1D1DB}\x{1D1DC}\x{1D1DD}\x{1D200}\x{1D201}\x{1D202}' .
'\x{1D203}\x{1D204}\x{1D205}\x{1D206}\x{1D207}\x{1D208}\x{1D209}' .
'\x{1D20A}\x{1D20B}\x{1D20C}\x{1D20D}\x{1D20E}\x{1D20F}\x{1D210}' .
'\x{1D211}\x{1D212}\x{1D213}\x{1D214}\x{1D215}\x{1D216}\x{1D217}' .
'\x{1D218}\x{1D219}\x{1D21A}\x{1D21B}\x{1D21C}\x{1D21D}\x{1D21E}' .
'\x{1D21F}\x{1D220}\x{1D221}\x{1D222}\x{1D223}\x{1D224}\x{1D225}' .
'\x{1D226}\x{1D227}\x{1D228}\x{1D229}\x{1D22A}\x{1D22B}\x{1D22C}' .
'\x{1D22D}\x{1D22E}\x{1D22F}\x{1D230}\x{1D231}\x{1D232}\x{1D233}' .
'\x{1D234}\x{1D235}\x{1D236}\x{1D237}\x{1D238}\x{1D239}\x{1D23A}' .
'\x{1D23B}\x{1D23C}\x{1D23D}\x{1D23E}\x{1D23F}\x{1D240}\x{1D241}' .
'\x{1D245}\x{1D300}\x{1D301}\x{1D302}\x{1D303}\x{1D304}\x{1D305}' .
'\x{1D306}\x{1D307}\x{1D308}\x{1D309}\x{1D30A}\x{1D30B}\x{1D30C}' .
'\x{1D30D}\x{1D30E}\x{1D30F}\x{1D310}\x{1D311}\x{1D312}\x{1D313}' .
'\x{1D314}\x{1D315}\x{1D316}\x{1D317}\x{1D318}\x{1D319}\x{1D31A}' .
'\x{1D31B}\x{1D31C}\x{1D31D}\x{1D31E}\x{1D31F}\x{1D320}\x{1D321}' .
'\x{1D322}\x{1D323}\x{1D324}\x{1D325}\x{1D326}\x{1D327}\x{1D328}' .
'\x{1D329}\x{1D32A}\x{1D32B}\x{1D32C}\x{1D32D}\x{1D32E}\x{1D32F}' .
'\x{1D330}\x{1D331}\x{1D332}\x{1D333}\x{1D334}\x{1D335}\x{1D336}' .
'\x{1D337}\x{1D338}\x{1D339}\x{1D33A}\x{1D33B}\x{1D33C}\x{1D33D}' .
'\x{1D33E}\x{1D33F}\x{1D340}\x{1D341}\x{1D342}\x{1D343}\x{1D344}' .
'\x{1D345}\x{1D346}\x{1D347}\x{1D348}\x{1D349}\x{1D34A}\x{1D34B}' .
'\x{1D34C}\x{1D34D}\x{1D34E}\x{1D34F}\x{1D350}\x{1D351}\x{1D352}' .
'\x{1D353}\x{1D354}\x{1D355}\x{1D356}\x{1F000}\x{1F001}\x{1F002}' .
'\x{1F003}\x{1F004}\x{1F005}\x{1F006}\x{1F007}\x{1F008}\x{1F009}' .
'\x{1F00A}\x{1F00B}\x{1F00C}\x{1F00D}\x{1F00E}\x{1F00F}\x{1F010}' .
'\x{1F011}\x{1F012}\x{1F013}\x{1F014}\x{1F015}\x{1F016}\x{1F017}' .
'\x{1F018}\x{1F019}\x{1F01A}\x{1F01B}\x{1F01C}\x{1F01D}\x{1F01E}' .
'\x{1F01F}\x{1F020}\x{1F021}\x{1F022}\x{1F023}\x{1F024}\x{1F025}' .
'\x{1F026}\x{1F027}\x{1F028}\x{1F029}\x{1F02A}\x{1F02B}\x{1F030}' .
'\x{1F031}\x{1F032}\x{1F033}\x{1F034}\x{1F035}\x{1F036}\x{1F037}' .
'\x{1F038}\x{1F039}\x{1F03A}\x{1F03B}\x{1F03C}\x{1F03D}\x{1F03E}' .
'\x{1F03F}\x{1F040}\x{1F041}\x{1F042}\x{1F043}\x{1F044}\x{1F045}' .
'\x{1F046}\x{1F047}\x{1F048}\x{1F049}\x{1F04A}\x{1F04B}\x{1F04C}' .
'\x{1F04D}\x{1F04E}\x{1F04F}\x{1F050}\x{1F051}\x{1F052}\x{1F053}' .
'\x{1F054}\x{1F055}\x{1F056}\x{1F057}\x{1F058}\x{1F059}\x{1F05A}' .
'\x{1F05B}\x{1F05C}\x{1F05D}\x{1F05E}\x{1F05F}\x{1F060}\x{1F061}' .
'\x{1F062}\x{1F063}\x{1F064}\x{1F065}\x{1F066}\x{1F067}\x{1F068}' .
'\x{1F069}\x{1F06A}\x{1F06B}\x{1F06C}\x{1F06D}\x{1F06E}\x{1F06F}' .
'\x{1F070}\x{1F071}\x{1F072}\x{1F073}\x{1F074}\x{1F075}\x{1F076}' .
'\x{1F077}\x{1F078}\x{1F079}\x{1F07A}\x{1F07B}\x{1F07C}\x{1F07D}' .
'\x{1F07E}\x{1F07F}\x{1F080}\x{1F081}\x{1F082}\x{1F083}\x{1F084}' .
'\x{1F085}\x{1F086}\x{1F087}\x{1F088}\x{1F089}\x{1F08A}\x{1F08B}' .
'\x{1F08C}\x{1F08D}\x{1F08E}\x{1F08F}\x{1F090}\x{1F091}\x{1F092}' .
'\x{1F093}\x{1F0A0}\x{1F0A1}\x{1F0A2}\x{1F0A3}\x{1F0A4}\x{1F0A5}' .
'\x{1F0A6}\x{1F0A7}\x{1F0A8}\x{1F0A9}\x{1F0AA}\x{1F0AB}\x{1F0AC}' .
'\x{1F0AD}\x{1F0AE}\x{1F0B1}\x{1F0B2}\x{1F0B3}\x{1F0B4}\x{1F0B5}' .
'\x{1F0B6}\x{1F0B7}\x{1F0B8}\x{1F0B9}\x{1F0BA}\x{1F0BB}\x{1F0BC}' .
'\x{1F0BD}\x{1F0BE}\x{1F0C1}\x{1F0C2}\x{1F0C3}\x{1F0C4}\x{1F0C5}' .
'\x{1F0C6}\x{1F0C7}\x{1F0C8}\x{1F0C9}\x{1F0CA}\x{1F0CB}\x{1F0CC}' .
'\x{1F0CD}\x{1F0CE}\x{1F0CF}\x{1F0D1}\x{1F0D2}\x{1F0D3}\x{1F0D4}' .
'\x{1F0D5}\x{1F0D6}\x{1F0D7}\x{1F0D8}\x{1F0D9}\x{1F0DA}\x{1F0DB}' .
'\x{1F0DC}\x{1F0DD}\x{1F0DE}\x{1F0DF}\x{1F110}\x{1F111}\x{1F112}' .
'\x{1F113}\x{1F114}\x{1F115}\x{1F116}\x{1F117}\x{1F118}\x{1F119}' .
'\x{1F11A}\x{1F11B}\x{1F11C}\x{1F11D}\x{1F11E}\x{1F11F}\x{1F120}' .
'\x{1F121}\x{1F122}\x{1F123}\x{1F124}\x{1F125}\x{1F126}\x{1F127}' .
'\x{1F128}\x{1F129}\x{1F12A}\x{1F12B}\x{1F12C}\x{1F12D}\x{1F12E}' .
'\x{1F130}\x{1F131}\x{1F132}\x{1F133}\x{1F134}\x{1F135}\x{1F136}' .
'\x{1F137}\x{1F138}\x{1F139}\x{1F13A}\x{1F13B}\x{1F13C}\x{1F13D}' .
'\x{1F13E}\x{1F13F}\x{1F140}\x{1F141}\x{1F142}\x{1F143}\x{1F144}' .
'\x{1F145}\x{1F146}\x{1F147}\x{1F148}\x{1F149}\x{1F14A}\x{1F14B}' .
'\x{1F14C}\x{1F14D}\x{1F14E}\x{1F14F}\x{1F150}\x{1F151}\x{1F152}' .
'\x{1F153}\x{1F154}\x{1F155}\x{1F156}\x{1F157}\x{1F158}\x{1F159}' .
'\x{1F15A}\x{1F15B}\x{1F15C}\x{1F15D}\x{1F15E}\x{1F15F}\x{1F160}' .
'\x{1F161}\x{1F162}\x{1F163}\x{1F164}\x{1F165}\x{1F166}\x{1F167}' .
'\x{1F168}\x{1F169}\x{1F16A}\x{1F16B}\x{1F170}\x{1F171}\x{1F172}' .
'\x{1F173}\x{1F174}\x{1F175}\x{1F176}\x{1F177}\x{1F178}\x{1F179}' .
'\x{1F17A}\x{1F17B}\x{1F17C}\x{1F17D}\x{1F17E}\x{1F17F}\x{1F180}' .
'\x{1F181}\x{1F182}\x{1F183}\x{1F184}\x{1F185}\x{1F186}\x{1F187}' .
'\x{1F188}\x{1F189}\x{1F18A}\x{1F18B}\x{1F18C}\x{1F18D}\x{1F18E}' .
'\x{1F18F}\x{1F190}\x{1F191}\x{1F192}\x{1F193}\x{1F194}\x{1F195}' .
'\x{1F196}\x{1F197}\x{1F198}\x{1F199}\x{1F19A}\x{1F1E6}\x{1F1E7}' .
'\x{1F1E8}\x{1F1E9}\x{1F1EA}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}' .
'\x{1F1EF}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F4}\x{1F1F5}' .
'\x{1F1F6}\x{1F1F7}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}\x{1F1FC}' .
'\x{1F1FD}\x{1F1FE}\x{1F1FF}\x{1F200}\x{1F201}\x{1F202}\x{1F210}' .
'\x{1F211}\x{1F212}\x{1F213}\x{1F214}\x{1F215}\x{1F216}\x{1F217}' .
'\x{1F218}\x{1F219}\x{1F21A}\x{1F21B}\x{1F21C}\x{1F21D}\x{1F21E}' .
'\x{1F21F}\x{1F220}\x{1F221}\x{1F222}\x{1F223}\x{1F224}\x{1F225}' .
'\x{1F226}\x{1F227}\x{1F228}\x{1F229}\x{1F22A}\x{1F22B}\x{1F22C}' .
'\x{1F22D}\x{1F22E}\x{1F22F}\x{1F230}\x{1F231}\x{1F232}\x{1F233}' .
'\x{1F234}\x{1F235}\x{1F236}\x{1F237}\x{1F238}\x{1F239}\x{1F23A}' .
'\x{1F240}\x{1F241}\x{1F242}\x{1F243}\x{1F244}\x{1F245}\x{1F246}' .
'\x{1F247}\x{1F248}\x{1F250}\x{1F251}\x{1F300}\x{1F301}\x{1F302}' .
'\x{1F303}\x{1F304}\x{1F305}\x{1F306}\x{1F307}\x{1F308}\x{1F309}' .
'\x{1F30A}\x{1F30B}\x{1F30C}\x{1F30D}\x{1F30E}\x{1F30F}\x{1F310}' .
'\x{1F311}\x{1F312}\x{1F313}\x{1F314}\x{1F315}\x{1F316}\x{1F317}' .
'\x{1F318}\x{1F319}\x{1F31A}\x{1F31B}\x{1F31C}\x{1F31D}\x{1F31E}' .
'\x{1F31F}\x{1F320}\x{1F330}\x{1F331}\x{1F332}\x{1F333}\x{1F334}' .
'\x{1F335}\x{1F337}\x{1F338}\x{1F339}\x{1F33A}\x{1F33B}\x{1F33C}' .
'\x{1F33D}\x{1F33E}\x{1F33F}\x{1F340}\x{1F341}\x{1F342}\x{1F343}' .
'\x{1F344}\x{1F345}\x{1F346}\x{1F347}\x{1F348}\x{1F349}\x{1F34A}' .
'\x{1F34B}\x{1F34C}\x{1F34D}\x{1F34E}\x{1F34F}\x{1F350}\x{1F351}' .
'\x{1F352}\x{1F353}\x{1F354}\x{1F355}\x{1F356}\x{1F357}\x{1F358}' .
'\x{1F359}\x{1F35A}\x{1F35B}\x{1F35C}\x{1F35D}\x{1F35E}\x{1F35F}' .
'\x{1F360}\x{1F361}\x{1F362}\x{1F363}\x{1F364}\x{1F365}\x{1F366}' .
'\x{1F367}\x{1F368}\x{1F369}\x{1F36A}\x{1F36B}\x{1F36C}\x{1F36D}' .
'\x{1F36E}\x{1F36F}\x{1F370}\x{1F371}\x{1F372}\x{1F373}\x{1F374}' .
'\x{1F375}\x{1F376}\x{1F377}\x{1F378}\x{1F379}\x{1F37A}\x{1F37B}' .
'\x{1F37C}\x{1F380}\x{1F381}\x{1F382}\x{1F383}\x{1F384}\x{1F385}' .
'\x{1F386}\x{1F387}\x{1F388}\x{1F389}\x{1F38A}\x{1F38B}\x{1F38C}' .
'\x{1F38D}\x{1F38E}\x{1F38F}\x{1F390}\x{1F391}\x{1F392}\x{1F393}' .
'\x{1F3A0}\x{1F3A1}\x{1F3A2}\x{1F3A3}\x{1F3A4}\x{1F3A5}\x{1F3A6}' .
'\x{1F3A7}\x{1F3A8}\x{1F3A9}\x{1F3AA}\x{1F3AB}\x{1F3AC}\x{1F3AD}' .
'\x{1F3AE}\x{1F3AF}\x{1F3B0}\x{1F3B1}\x{1F3B2}\x{1F3B3}\x{1F3B4}' .
'\x{1F3B5}\x{1F3B6}\x{1F3B7}\x{1F3B8}\x{1F3B9}\x{1F3BA}\x{1F3BB}' .
'\x{1F3BC}\x{1F3BD}\x{1F3BE}\x{1F3BF}\x{1F3C0}\x{1F3C1}\x{1F3C2}' .
'\x{1F3C3}\x{1F3C4}\x{1F3C6}\x{1F3C7}\x{1F3C8}\x{1F3C9}\x{1F3CA}' .
'\x{1F3E0}\x{1F3E1}\x{1F3E2}\x{1F3E3}\x{1F3E4}\x{1F3E5}\x{1F3E6}' .
'\x{1F3E7}\x{1F3E8}\x{1F3E9}\x{1F3EA}\x{1F3EB}\x{1F3EC}\x{1F3ED}' .
'\x{1F3EE}\x{1F3EF}\x{1F3F0}\x{1F400}\x{1F401}\x{1F402}\x{1F403}' .
'\x{1F404}\x{1F405}\x{1F406}\x{1F407}\x{1F408}\x{1F409}\x{1F40A}' .
'\x{1F40B}\x{1F40C}\x{1F40D}\x{1F40E}\x{1F40F}\x{1F410}\x{1F411}' .
'\x{1F412}\x{1F413}\x{1F414}\x{1F415}\x{1F416}\x{1F417}\x{1F418}' .
'\x{1F419}\x{1F41A}\x{1F41B}\x{1F41C}\x{1F41D}\x{1F41E}\x{1F41F}' .
'\x{1F420}\x{1F421}\x{1F422}\x{1F423}\x{1F424}\x{1F425}\x{1F426}' .
'\x{1F427}\x{1F428}\x{1F429}\x{1F42A}\x{1F42B}\x{1F42C}\x{1F42D}' .
'\x{1F42E}\x{1F42F}\x{1F430}\x{1F431}\x{1F432}\x{1F433}\x{1F434}' .
'\x{1F435}\x{1F436}\x{1F437}\x{1F438}\x{1F439}\x{1F43A}\x{1F43B}' .
'\x{1F43C}\x{1F43D}\x{1F43E}\x{1F440}\x{1F442}\x{1F443}\x{1F444}' .
'\x{1F445}\x{1F446}\x{1F447}\x{1F448}\x{1F449}\x{1F44A}\x{1F44B}' .
'\x{1F44C}\x{1F44D}\x{1F44E}\x{1F44F}\x{1F450}\x{1F451}\x{1F452}' .
'\x{1F453}\x{1F454}\x{1F455}\x{1F456}\x{1F457}\x{1F458}\x{1F459}' .
'\x{1F45A}\x{1F45B}\x{1F45C}\x{1F45D}\x{1F45E}\x{1F45F}\x{1F460}' .
'\x{1F461}\x{1F462}\x{1F463}\x{1F464}\x{1F465}\x{1F466}\x{1F467}' .
'\x{1F468}\x{1F469}\x{1F46A}\x{1F46B}\x{1F46C}\x{1F46D}\x{1F46E}' .
'\x{1F46F}\x{1F470}\x{1F471}\x{1F472}\x{1F473}\x{1F474}\x{1F475}' .
'\x{1F476}\x{1F477}\x{1F478}\x{1F479}\x{1F47A}\x{1F47B}\x{1F47C}' .
'\x{1F47D}\x{1F47E}\x{1F47F}\x{1F480}\x{1F481}\x{1F482}\x{1F483}' .
'\x{1F484}\x{1F485}\x{1F486}\x{1F487}\x{1F488}\x{1F489}\x{1F48A}' .
'\x{1F48B}\x{1F48C}\x{1F48D}\x{1F48E}\x{1F48F}\x{1F490}\x{1F491}' .
'\x{1F492}\x{1F493}\x{1F494}\x{1F495}\x{1F496}\x{1F497}\x{1F498}' .
'\x{1F499}\x{1F49A}\x{1F49B}\x{1F49C}\x{1F49D}\x{1F49E}\x{1F49F}' .
'\x{1F4A0}\x{1F4A1}\x{1F4A2}\x{1F4A3}\x{1F4A4}\x{1F4A5}\x{1F4A6}' .
'\x{1F4A7}\x{1F4A8}\x{1F4A9}\x{1F4AA}\x{1F4AB}\x{1F4AC}\x{1F4AD}' .
'\x{1F4AE}\x{1F4AF}\x{1F4B0}\x{1F4B1}\x{1F4B2}\x{1F4B3}\x{1F4B4}' .
'\x{1F4B5}\x{1F4B6}\x{1F4B7}\x{1F4B8}\x{1F4B9}\x{1F4BA}\x{1F4BB}' .
'\x{1F4BC}\x{1F4BD}\x{1F4BE}\x{1F4BF}\x{1F4C0}\x{1F4C1}\x{1F4C2}' .
'\x{1F4C3}\x{1F4C4}\x{1F4C5}\x{1F4C6}\x{1F4C7}\x{1F4C8}\x{1F4C9}' .
'\x{1F4CA}\x{1F4CB}\x{1F4CC}\x{1F4CD}\x{1F4CE}\x{1F4CF}\x{1F4D0}' .
'\x{1F4D1}\x{1F4D2}\x{1F4D3}\x{1F4D4}\x{1F4D5}\x{1F4D6}\x{1F4D7}' .
'\x{1F4D8}\x{1F4D9}\x{1F4DA}\x{1F4DB}\x{1F4DC}\x{1F4DD}\x{1F4DE}' .
'\x{1F4DF}\x{1F4E0}\x{1F4E1}\x{1F4E2}\x{1F4E3}\x{1F4E4}\x{1F4E5}' .
'\x{1F4E6}\x{1F4E7}\x{1F4E8}\x{1F4E9}\x{1F4EA}\x{1F4EB}\x{1F4EC}' .
'\x{1F4ED}\x{1F4EE}\x{1F4EF}\x{1F4F0}\x{1F4F1}\x{1F4F2}\x{1F4F3}' .
'\x{1F4F4}\x{1F4F5}\x{1F4F6}\x{1F4F7}\x{1F4F9}\x{1F4FA}\x{1F4FB}' .
'\x{1F4FC}\x{1F500}\x{1F501}\x{1F502}\x{1F503}\x{1F504}\x{1F505}' .
'\x{1F506}\x{1F507}\x{1F508}\x{1F509}\x{1F50A}\x{1F50B}\x{1F50C}' .
'\x{1F50D}\x{1F50E}\x{1F50F}\x{1F510}\x{1F511}\x{1F512}\x{1F513}' .
'\x{1F514}\x{1F515}\x{1F516}\x{1F517}\x{1F518}\x{1F519}\x{1F51A}' .
'\x{1F51B}\x{1F51C}\x{1F51D}\x{1F51E}\x{1F51F}\x{1F520}\x{1F521}' .
'\x{1F522}\x{1F523}\x{1F524}\x{1F525}\x{1F526}\x{1F527}\x{1F528}' .
'\x{1F529}\x{1F52A}\x{1F52B}\x{1F52C}\x{1F52D}\x{1F52E}\x{1F52F}' .
'\x{1F530}\x{1F531}\x{1F532}\x{1F533}\x{1F534}\x{1F535}\x{1F536}' .
'\x{1F537}\x{1F538}\x{1F539}\x{1F53A}\x{1F53B}\x{1F53C}\x{1F53D}' .
'\x{1F540}\x{1F541}\x{1F542}\x{1F543}\x{1F550}\x{1F551}\x{1F552}' .
'\x{1F553}\x{1F554}\x{1F555}\x{1F556}\x{1F557}\x{1F558}\x{1F559}' .
'\x{1F55A}\x{1F55B}\x{1F55C}\x{1F55D}\x{1F55E}\x{1F55F}\x{1F560}' .
'\x{1F561}\x{1F562}\x{1F563}\x{1F564}\x{1F565}\x{1F566}\x{1F567}' .
'\x{1F5FB}\x{1F5FC}\x{1F5FD}\x{1F5FE}\x{1F5FF}\x{1F600}\x{1F601}' .
'\x{1F602}\x{1F603}\x{1F604}\x{1F605}\x{1F606}\x{1F607}\x{1F608}' .
'\x{1F609}\x{1F60A}\x{1F60B}\x{1F60C}\x{1F60D}\x{1F60E}\x{1F60F}' .
'\x{1F610}\x{1F611}\x{1F612}\x{1F613}\x{1F614}\x{1F615}\x{1F616}' .
'\x{1F617}\x{1F618}\x{1F619}\x{1F61A}\x{1F61B}\x{1F61C}\x{1F61D}' .
'\x{1F61E}\x{1F61F}\x{1F620}\x{1F621}\x{1F622}\x{1F623}\x{1F624}' .
'\x{1F625}\x{1F626}\x{1F627}\x{1F628}\x{1F629}\x{1F62A}\x{1F62B}' .
'\x{1F62C}\x{1F62D}\x{1F62E}\x{1F62F}\x{1F630}\x{1F631}\x{1F632}' .
'\x{1F633}\x{1F634}\x{1F635}\x{1F636}\x{1F637}\x{1F638}\x{1F639}' .
'\x{1F63A}\x{1F63B}\x{1F63C}\x{1F63D}\x{1F63E}\x{1F63F}\x{1F640}' .
'\x{1F645}\x{1F646}\x{1F647}\x{1F648}\x{1F649}\x{1F64A}\x{1F64B}' .
'\x{1F64C}\x{1F64D}\x{1F64E}\x{1F64F}\x{1F680}\x{1F681}\x{1F682}' .
'\x{1F683}\x{1F684}\x{1F685}\x{1F686}\x{1F687}\x{1F688}\x{1F689}' .
'\x{1F68A}\x{1F68B}\x{1F68C}\x{1F68D}\x{1F68E}\x{1F68F}\x{1F690}' .
'\x{1F691}\x{1F692}\x{1F693}\x{1F694}\x{1F695}\x{1F696}\x{1F697}' .
'\x{1F698}\x{1F699}\x{1F69A}\x{1F69B}\x{1F69C}\x{1F69D}\x{1F69E}' .
'\x{1F69F}\x{1F6A0}\x{1F6A1}\x{1F6A2}\x{1F6A3}\x{1F6A4}\x{1F6A5}' .
'\x{1F6A6}\x{1F6A7}\x{1F6A8}\x{1F6A9}\x{1F6AA}\x{1F6AB}\x{1F6AC}' .
'\x{1F6AD}\x{1F6AE}\x{1F6AF}\x{1F6B0}\x{1F6B1}\x{1F6B2}\x{1F6B3}' .
'\x{1F6B4}\x{1F6B5}\x{1F6B6}\x{1F6B7}\x{1F6B8}\x{1F6B9}\x{1F6BA}' .
'\x{1F6BB}\x{1F6BC}\x{1F6BD}\x{1F6BE}\x{1F6BF}\x{1F6C0}\x{1F6C1}' .
'\x{1F6C2}\x{1F6C3}\x{1F6C4}\x{1F6C5}\x{1F700}\x{1F701}\x{1F702}' .
'\x{1F703}\x{1F704}\x{1F705}\x{1F706}\x{1F707}\x{1F708}\x{1F709}' .
'\x{1F70A}\x{1F70B}\x{1F70C}\x{1F70D}\x{1F70E}\x{1F70F}\x{1F710}' .
'\x{1F711}\x{1F712}\x{1F713}\x{1F714}\x{1F715}\x{1F716}\x{1F717}' .
'\x{1F718}\x{1F719}\x{1F71A}\x{1F71B}\x{1F71C}\x{1F71D}\x{1F71E}' .
'\x{1F71F}\x{1F720}\x{1F721}\x{1F722}\x{1F723}\x{1F724}\x{1F725}' .
'\x{1F726}\x{1F727}\x{1F728}\x{1F729}\x{1F72A}\x{1F72B}\x{1F72C}' .
'\x{1F72D}\x{1F72E}\x{1F72F}\x{1F730}\x{1F731}\x{1F732}\x{1F733}' .
'\x{1F734}\x{1F735}\x{1F736}\x{1F737}\x{1F738}\x{1F739}\x{1F73A}' .
'\x{1F73B}\x{1F73C}\x{1F73D}\x{1F73E}\x{1F73F}\x{1F740}\x{1F741}' .
'\x{1F742}\x{1F743}\x{1F744}\x{1F745}\x{1F746}\x{1F747}\x{1F748}' .
'\x{1F749}\x{1F74A}\x{1F74B}\x{1F74C}\x{1F74D}\x{1F74E}\x{1F74F}' .
'\x{1F750}\x{1F751}\x{1F752}\x{1F753}\x{1F754}\x{1F755}\x{1F756}' .
'\x{1F757}\x{1F758}\x{1F759}\x{1F75A}\x{1F75B}\x{1F75C}\x{1F75D}' .
'\x{1F75E}\x{1F75F}\x{1F760}\x{1F761}\x{1F762}\x{1F763}\x{1F764}' .
'\x{1F765}\x{1F766}\x{1F767}\x{1F768}\x{1F769}\x{1F76A}\x{1F76B}' .
'\x{1F76C}\x{1F76D}\x{1F76E}\x{1F76F}\x{1F770}\x{1F771}\x{1F772}' .
'\x{1F773}' .




'\x{2028}' .




'\x{2029}' .




'\x{0020}\x{00A0}\x{1680}\x{2000}\x{2001}\x{2002}\x{2003}' .
'\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}' .
'\x{202F}\x{205F}\x{3000}' .










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

>>>>>>> Wip converting unicode symbols.
        break;
    }
    return FALSE;
  }

  /**
>>>>>>> Wip converting unicode symbols.
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
      case 'Cs':
        return Cs::getRegularExpression();
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

      'Mc' => t("Mark, Spacing Combining Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mc/list.htm"))),
      'Me' => t("Mark, Enclosing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Me/list.htm"))),
      'Mn' => t("Mark, Nonspacing Characters (!link)", array("!link" => l("View","http://www.fileformat.info/info/unicode/category/Mn/list.htm"))),

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
