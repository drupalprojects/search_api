<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "tokenizer",
 *   label = @Translation("Tokenizer processor"),
 *   description = @Translation("Splits strings in to tokens.")
 * )
 */
class Tokenizer extends FieldsProcessorPluginBase {

  /**
   * @var string
   */
  protected $spaces;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'spaces' => "",
      'overlap_cjk' => TRUE,
      'minimum_word_size' => 3,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['spaces'] = array(
      '#type' => 'textfield',
      '#title' => t('Whitespace characters'),
      '#description' => t('Specify the characters that should be regarded as whitespace and therefore used as word-delimiters. ' .
          'Specify the characters as a <a href="@link">PCRE character class</a>. ' .
          'Note: For non-English content, the default setting might not be suitable.',
          array('@link' => url('http://www.php.net/manual/en/regexp.reference.character-classes.php'))),
      '#default_value' => $this->configuration['spaces'],
    );

    $form['overlap_cjk'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Simple CJK handling'),
      '#default_value' => $this->configuration['overlap_cjk'],
      '#description' => $this->t('Whether to apply a simple Chinese/Japanese/Korean tokenizer based on overlapping sequences. Does not affect other languages.')
    );

    $form['minimum_word_size'] = array(
      '#type' => 'number',
      '#title' => $this->t('Minimum word length to index'),
      '#default_value' => $this->configuration['minimum_word_size'],
      '#min' => 1,
      '#max' => 1000,
      '#description' => $this->t('The number of characters a word has to be to be indexed. A lower setting means better search result ranking, but also a larger database. Each search query must contain at least one keyword that is this size (or longer).')
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $spaces = str_replace('/', '\/', $form_state['values']['spaces']);
    if (@preg_match('/(' . $spaces . ')+/u', '') === FALSE) {
      $el = $form['spaces'];
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
   * Matches all 'N' Unicode character classes (numbers)
   *
   * @return string
   */
  private function getPregClassNumbers() {
    return '\x{30}-\x{39}\x{b2}\x{b3}\x{b9}\x{bc}-\x{be}\x{660}-\x{669}\x{6f0}-\x{6f9}' .
      '\x{966}-\x{96f}\x{9e6}-\x{9ef}\x{9f4}-\x{9f9}\x{a66}-\x{a6f}\x{ae6}-\x{aef}' .
      '\x{b66}-\x{b6f}\x{be7}-\x{bf2}\x{c66}-\x{c6f}\x{ce6}-\x{cef}\x{d66}-\x{d6f}' .
      '\x{e50}-\x{e59}\x{ed0}-\x{ed9}\x{f20}-\x{f33}\x{1040}-\x{1049}\x{1369}-' .
      '\x{137c}\x{16ee}-\x{16f0}\x{17e0}-\x{17e9}\x{17f0}-\x{17f9}\x{1810}-\x{1819}' .
      '\x{1946}-\x{194f}\x{2070}\x{2074}-\x{2079}\x{2080}-\x{2089}\x{2153}-\x{2183}' .
      '\x{2460}-\x{249b}\x{24ea}-\x{24ff}\x{2776}-\x{2793}\x{3007}\x{3021}-\x{3029}' .
      '\x{3038}-\x{303a}\x{3192}-\x{3195}\x{3220}-\x{3229}\x{3251}-\x{325f}\x{3280}-' .
      '\x{3289}\x{32b1}-\x{32bf}\x{ff10}-\x{ff19}';
  }

  /**
   * Matches all 'P' Unicode character classes (punctuation)
   *
   * @return string
   */
  private function getPregClassPunctuation() {
    return '\x{21}-\x{23}\x{25}-\x{2a}\x{2c}-\x{2f}\x{3a}\x{3b}\x{3f}\x{40}\x{5b}-\x{5d}' .
      '\x{5f}\x{7b}\x{7d}\x{a1}\x{ab}\x{b7}\x{bb}\x{bf}\x{37e}\x{387}\x{55a}-\x{55f}' .
      '\x{589}\x{58a}\x{5be}\x{5c0}\x{5c3}\x{5f3}\x{5f4}\x{60c}\x{60d}\x{61b}\x{61f}' .
      '\x{66a}-\x{66d}\x{6d4}\x{700}-\x{70d}\x{964}\x{965}\x{970}\x{df4}\x{e4f}' .
      '\x{e5a}\x{e5b}\x{f04}-\x{f12}\x{f3a}-\x{f3d}\x{f85}\x{104a}-\x{104f}\x{10fb}' .
      '\x{1361}-\x{1368}\x{166d}\x{166e}\x{169b}\x{169c}\x{16eb}-\x{16ed}\x{1735}' .
      '\x{1736}\x{17d4}-\x{17d6}\x{17d8}-\x{17da}\x{1800}-\x{180a}\x{1944}\x{1945}' .
      '\x{2010}-\x{2027}\x{2030}-\x{2043}\x{2045}-\x{2051}\x{2053}\x{2054}\x{2057}' .
      '\x{207d}\x{207e}\x{208d}\x{208e}\x{2329}\x{232a}\x{23b4}-\x{23b6}\x{2768}-' .
      '\x{2775}\x{27e6}-\x{27eb}\x{2983}-\x{2998}\x{29d8}-\x{29db}\x{29fc}\x{29fd}' .
      '\x{3001}-\x{3003}\x{3008}-\x{3011}\x{3014}-\x{301f}\x{3030}\x{303d}\x{30a0}' .
      '\x{30fb}\x{fd3e}\x{fd3f}\x{fe30}-\x{fe52}\x{fe54}-\x{fe61}\x{fe63}\x{fe68}' .
      '\x{fe6a}\x{fe6b}\x{ff01}-\x{ff03}\x{ff05}-\x{ff0a}\x{ff0c}-\x{ff0f}\x{ff1a}' .
      '\x{ff1b}\x{ff1f}\x{ff20}\x{ff3b}-\x{ff3d}\x{ff3f}\x{ff5b}\x{ff5d}\x{ff5f}-' .
      '\x{ff65}';
  }

  /**
   * Matches CJK (Chinese, Japanese, Korean) letter-like characters.
   *
   * This list is derived from the "East Asian Scripts" section of
   * http://www.unicode.org/charts/index.html, as well as a comment on
   * http://unicode.org/reports/tr11/tr11-11.html listing some character
   * ranges that are reserved for additional CJK ideographs.
   *
   * The character ranges do not include numbers, punctuation, or symbols, since
   * these are handled separately in search. Note that radicals and strokes are
   * considered symbols. (See
   * http://www.unicode.org/Public/UNIDATA/extracted/DerivedGeneralCategory.txt)
   *
   * @see search_expand_cjk() (Core Search of Drupal 8)
   *
   * @return string
   */
  private function getPregClassCJK() {
    return '\x{1100}-\x{11FF}\x{3040}-\x{309F}\x{30A1}-\x{318E}' .
      '\x{31A0}-\x{31B7}\x{31F0}-\x{31FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FCF}' .
      '\x{A000}-\x{A48F}\x{A4D0}-\x{A4FD}\x{A960}-\x{A97F}\x{AC00}-\x{D7FF}' .
      '\x{F900}-\x{FAFF}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}\x{FF66}-\x{FFDC}' .
      '\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}';
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, &$type) {
    $this->prepare();
    // Split it based on our configuration
    $value = $this->split($value);
  }

  /**
   * Simplifies a string according to indexing rules.
   *
   * @param $text
   *   Text to simplify.
   * @param $langcode
   *
   * @see search.module
   *
   * @return string
   *   Simplified text.
   */
  protected function simplifyText($text, $langcode = NULL) {
    // Decode entities to UTF-8
    $text = String::decodeEntities($text);

    // Lowercase
    $text = Unicode::strtolower($text);

    // Simple CJK handling
    if ($this->configuration['overlap_cjk']) {
      $text = preg_replace_callback('/[' . $this->getPregClassCJK() . ']+/u', array(&$this, 'expand_cjk'), $text);
    }

    // To improve searching for numerical data such as dates, IP addresses
    // or version numbers, we consider a group of numerical characters
    // separated only by punctuation characters to be one piece.
    // This also means that searching for e.g. '20/03/1984' also returns
    // results with '20-03-1984' in them.
    // Readable regexp: ([number]+)[punctuation]+(?=[number])
    $text = preg_replace('/([' . $this->getPregClassNumbers() . ']+)[' . $this->getPregClassPunctuation() . ']+(?=[' . $this->getPregClassNumbers() . '])/u', '\1', $text);

    // Multiple dot and dash groups are word boundaries and replaced with space.
    // No need to use the unicode modifer here because 0-127 ASCII characters
    // can't match higher UTF-8 characters as the leftmost bit of those are 1.
    $text = preg_replace('/[.-]{2,}/', ' ', $text);

    // The dot, underscore and dash are simply removed. This allows meaningful
    // search behavior with acronyms and URLs. See unicode note directly above.
    $text = preg_replace('/[._-]+/', '', $text);

    // With the exception of the rules above, we consider all punctuation,
    // marks, spacers, etc, to be a word boundary.
    $text = preg_replace('/[' . Unicode::PREG_CLASS_WORD_BOUNDARY . ']+/u', ' ', $text);
    // @todo implement   array_walk($words, '_search_index_truncate'); like Drupal Core Search
    return $text;
  }

  /**
   * Splits CJK (Chinese, Japanese, Korean) text into tokens.
   *
   * The Search module matches exact words, where a word is defined to be a
   * sequence of characters delimited by spaces or punctuation. CJK languages are
   * written in long strings of characters, though, not split up into words. So
   * in order to allow search matching, we split up CJK text into tokens
   * consisting of consecutive, overlapping sequences of characters whose length
   * is equal to the 'minimum_word_size' variable. This tokenizing is only done
   * if the 'overlap_cjk' variable is TRUE.
   *
   * @param $matches
   *   This function is a callback for preg_replace_callback(), which is called
   *   from search_simplify(). So, $matches is an array of regular expression
   *   matches, which means that $matches[0] contains the matched text -- a
   *   string of CJK characters to tokenize.
   *
   * @return string
   *   Tokenized text, starting and ending with a space character.
   */
  protected function expand_cjk($matches) {
    $min = $this->configuration['minimum_word_size'];
    $str = $matches[0];
    $length = Unicode::strlen($str);
    // If the text is shorter than the minimum word size, don't tokenize it.
    if ($length <= $min) {
      return ' ' . $str . ' ';
    }
    $tokens = ' ';
    // Build a FIFO queue of characters.
    $chars = array();
    for ($i = 0; $i < $length; $i++) {
      // Add the next character off the beginning of the string to the queue.
      $current = Unicode::substr($str, 0, 1);
      $str = substr($str, strlen($current));
      $chars[] = $current;
      if ($i >= $min - 1) {
        // Make a token of $min characters, and add it to the token string.
        $tokens .= implode('', $chars) . ' ';
        // Shift out the first character in the queue.
        array_shift($chars);
      }
    }
    return $tokens;
  }

  /**
   * Simplifies and splits a string into tokens for indexing.
   */
  protected function split($text, $lang_code = NULL) {
    $value = array();
    // Process words
    $text = $this->simplifyText($text, $lang_code);

    // Split on the configured delimiter. If not configured - use spaces
    if ($this->spaces) {
      $arr = preg_split('/(' . $this->spaces . ')+/u', $text);
    }
    else {
      $arr = explode(' ', $text);
    }

    if (count($arr) > 1) {
      foreach ($arr as $token) {
        $value[] = array('value' => $token);
      }
    }
    else {
      $value[] = array('value' => $text);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $this->prepare();
      $value = trim($this->simplifyText($value));
    }
  }

  /**
   * Prepares the settings.
   */
  protected function prepare() {
    if (!isset($this->spaces)) {
      $this->spaces = str_replace('/', '\/', $this->configuration['spaces']);
    }
  }

}
