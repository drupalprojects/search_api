<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchAPI\Processor\HTMLFilter.
 */

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_html_filter_processor",
 *   label = @Translation("HTML Filter"),
 *   description = @Translation("Strips HTML tags from fulltext fields and decodes HTML entities. Use this processor when indexing HTML data, e.g., node bodies for certain text formats. The processor also allows to boost (or ignore) the contents of specific elements.")
 * )
 *
 */
class HTMLFilter extends FieldsProcessorPluginBase {

  /**
   * @var array
   */
  protected $tags = array();

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'title' => FALSE,
      'alt' => TRUE,
      'tags' => "
h1: 5
h2: 3
h3: 2
string: 2
b: 2
em: 1.5
u: 1.5",
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
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!empty($configuration['tags'])) {
      $this->tags = $this->parseTags($configuration['tags']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['title'] = array(
      '#type' => 'checkbox',
      '#title' => t('Index title attribute'),
      '#description' => t('If set, the contents of title attributes will be indexed.'),
      '#default_value' => $this->configuration['title'],
    );

    $form['alt'] = array(
      '#type' => 'checkbox',
      '#title' => t('Index alt attribute'),
      '#description' => t('If set, the alternative text of images will be indexed.'),
      '#default_value' => $this->configuration['alt'],
    );
    $t_args = array('!link' => l('YAML file format', 'https://api.drupal.org/api/drupal/core!vendor!symfony!yaml!Symfony!Component!Yaml!Yaml.php/function/Yaml::parse/8'));

    $form['tags'] = array(
      '#type' => 'textarea',
      '#title' => t('Tag boosts'),
      '#description' => t('Specify special boost values for certain HTML elements, in !link. The boost values of nested elements are multiplied, elements not mentioned will have the default boost value of 1. Assign a boost of 0 to ignore the text content of that HTML element.', $t_args),
      '#default_value' => $this->configuration['tags'],
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

    $values = $form_state['values'];
    if (empty($values['tags'])) {
      return;
    }
    $errors = array();
    if (!$tags = $this->parseTags($values['tags'])) {
      $errors[] = t("Tags is not valid YAML. See @link for information on how to write correctly formed YAML.", array('@link' => 'http://yaml.org'));
      $tags = array();
    }
    foreach ($tags as $key => $value) {
      if (is_array($value)) {
        $errors[] = t("Boost value for tag &lt;@tag&gt; can't be an array.", array('@tag' => $key));
      }
      elseif (!is_numeric($value)) {
        $errors[] = t("Boost value for tag &lt;@tag&gt; must be numeric.", array('@tag' => $key));
      }
      elseif ($value < 0) {
        $errors[] = t('Boost value for tag &lt;@tag&gt; must be non-negative.', array('@tag' => $key));
      }
    }
    if ($errors) {
      \Drupal::formBuilder()->setError($form['tags'], $form_state, implode("<br />\n", $errors));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value) {
    $tokenized_text = array();
    // Copy without reference
    $text = $value;
    // Put the title properties from html entities in the text blob.
    if ($this->configuration['title']) {
      $text = preg_replace('/((<[-a-z_]+[^>]+)\s+title\s*=\s*("([^"]+)"|\'([^\']+)\')([^>]*>))/i', '$4$5 $1', $text);
    }
    // Put the alt text as regular text in the text blob.
    if ($this->configuration['alt']) {
      $text = preg_replace('/(<img[^>]*\s+alt\s*=\s*("([^"]+)"|\'([^\']+)\')[^>]*>)/i', '$3$4 $1', $text);
    }
    // Get any other configured tags.
    if (!empty($this->configuration['tags'])) {
      $tags_exploded = '<' . implode('><', array_keys($this->tags)) . '>';
      // Let removed tags still delimit words.
      $text = str_replace(array('<', '>'), array(' <', '> '), $text);
      // Strip all tags except the ones configured
      $text = strip_tags($text, $tags_exploded);
      // Get rid of unnecessary space symbols.
      $get_tag_scores = $this->getValueAndScoreFromHTML($text);
      if (!empty($get_tag_scores)) {
        $tokenized_text = array_merge($tokenized_text, $get_tag_scores);
      }
    }
    // Add also everything as non boosted for the general field.
    $tokenized_text['content'] = array('value' => $text, 'score' => 1);

    // Clean up all the remaining tags and clean up the different sections.
    foreach ($tokenized_text as &$text) {
      $text['value'] = $this->stripHtmlTagsAndPunctuation($text['value']);
    }
    $value = array_values($tokenized_text);
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

  /**
   * Parses text and returns the different segments it found with their boosts.
   *
   * @param string $text
   *
   * @return array
   *  Array of parsed values, and their boost attached
   *  Values that were found that were not enclosed in tags should also get
   *  added but with a boost as 1
   */
  protected function getValueAndScoreFromHTML($text) {
    $ret = array();
    preg_match_all('@<(' . implode('|', array_keys($this->tags)) . ')[^>]*>(.*)</\1>@Ui', $text, $matches);

    foreach ($matches[1] as $key => $tag) {
      $tag = Unicode::strtolower($tag);

      if (!isset($ret[$tag])) {
        $ret[$tag] = array('value' => '', 'score' => '');
      }
      // We don't want to index links auto-generated by the url filter.
      if ($tag != 'a' || !preg_match('@(?:http://|https://|ftp://|mailto:|smb://|afp://|file://|gopher://|news://|ssl://|sslv2://|sslv3://|tls://|tcp://|udp://|www\.)[a-zA-Z0-9]+@', $matches[2][$key])) {
        $ret[$tag]['value'] .= $matches[2][$key];
        $ret[$tag]['score'] = (isset($this->tags[$tag]) ? $this->tags[$tag] : 1);
      }
    }
    return $ret;
  }

  /**
   * The following function strips a couple of things from the text that passes
   * in through the filter.
   *
   * - Strips invisible content from tags such as canvas, embed, iframe etc..
   * - Adds spaces before stripping tags to avoid the following scenario.
   *   test<p>test</p> => testtest. This should become test test.
   * - Decode html entities so we index characters and not html encoded
   *   entities.
   *   "I'll &quot;walk&quot;" to "I'll \"walk\"" to.
   * - Remove extra spaces. No need to index extra spaces.
   * - Also remove punctuation characters
   *
   * @param string $text
   *   The input to clean
   * @return string
   *   The clean text
   */
  protected function stripHtmlTagsAndPunctuation($text) {
    // Remove invisible content.
    $text = preg_replace('@<(applet|audio|canvas|command|embed|iframe|map|menu|noembed|noframes|noscript|script|style|svg|video)[^>]*>.*</\1>@siU', ' ', $text);
    // Add spaces before stripping tags to avoid running words together.
    $text = Xss::filter(str_replace(array('<', '>'), array(' <', '> '), $text), array());
    // Decode entities and then make safe any < or > characters.
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    // Remove extra spaces.
    $text = preg_replace('/\s+/s', ' ', $text);

    // Get our configuration
    $character_sets = $this->configuration['strip']['character_sets'];

    // Custom Extra Drupal Characters that we want to remove
    if (isset($character_sets['Do'])) {
      $character_set_regex = '!|\?|,|\.|:|;';
    }
    // Add a pipe so we don't end up with an invalid regular expression
    if (!empty($character_set_regex)) {
      $character_set_regex += "|" . $character_set_regex;
    }

    $character_set_regex += '\p{' . implode('}|\p{', $character_sets) . '}';
    $text = preg_replace('/\s(' . $character_set_regex . ')/s', '$1', $text);
    $text = preg_replace('/(\p{Ps}|¿|¡)\s/s', '$1', $text);
    $text = trim($text);
    return $text;
  }

  /**
   *  Parses an array of tags in YAML.
   *
   *  @param $yaml
   *    yaml represenation of array
   *
   *  @return array of tags
   */
  protected function parseTags($yaml) {
    try {
      $tags = Yaml::parse($yaml);
      unset($tags['br'], $tags['hr']);
    }
    catch(ParseException $exception) {
      //problem parsing, return empty array
      $tags = FALSE;
    }
    return $tags;
  }
}