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
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

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
      'tags' => array(
        'h1' => 5,
        'h2' => 3,
        'h3' => 2,
        'string' => 2,
        'b' => 2,
        'em' => 1.5,
        'u' => 1.5,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!empty($configuration['tags'])) {
      $this->tags = $configuration['tags'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = array();

    // Only include full text fields. Important as only those can be tokenized.
    $fields = $this->index->getFields();
    $field_options = array();
    $default_fields = array();
    if (isset($this->configuration['fields'])) {
      $default_fields = array_filter($this->configuration['fields']);
    }

    foreach ($fields as $name => $field) {
      if ($field->getType() == 'text') {
        if ($this->testType($field->getType())) {
          $field_options[$name] = $field->getPrefixedLabel();
          if (!isset($this->configuration['fields']) && $this->testField($name, $field)) {
            $default_fields[$name] = $name;
          }
        }
      }
    }

    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Enable this processor on the following fields'),
      '#options' => $field_options,
      '#default_value' => $default_fields,
    );

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

    $dumper = new Dumper();
    $tags = $dumper->dump($this->configuration['tags'], 2);
    $tags = str_replace('\r\n', "\n", $tags);
    $tags = str_replace('"', '', $tags);

    $form['tags'] = array(
      '#type' => 'textarea',
      '#title' => t('Tag boosts'),
      '#description' => t('Specify special boost values for certain HTML elements, in !link. The boost values of nested elements are multiplied, elements not mentioned will have the default boost value of 1. Assign a boost of 0 to ignore the text content of that HTML element.', $t_args),
      '#default_value' => $tags,
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
    $form_state['values']['tags'] = $tags;
    foreach ($tags as $key => $value) {
      $key = "<$key>";
      if (is_array($value)) {
        $errors[] = t("Boost value for tag @tag can't be an array.", array('@tag' => $key));
      }
      elseif (!is_numeric($value)) {
        $errors[] = t("Boost value for tag @tag must be numeric.", array('@tag' => $key));
      }
      elseif ($value < 0) {
        $errors[] = t('Boost value for tag @tag must be non-negative.', array('@tag' => $key));
      }
    }
    if ($errors) {
      \Drupal::formBuilder()->setError($form['tags'], $form_state, implode("<br />\n", $errors));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, &$type) {
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

    // Process the individual tags for text or tokenized_text types.
    if (!empty($this->configuration['tags']) && in_array($type, array('text', 'tokenized_text'))) {
      $tokenized_text = array();
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
      // Add also everything as non boosted for the general field.
      $tokenized_text['content'] = array('value' => $text, 'score' => 1);

      // Clean up all the remaining tags and clean up the different sections.
      foreach ($tokenized_text as &$token) {
        $token['value'] = $this->stripHtmlTagsAndPunctuation($token['value']);
      }
      $value = array_values($tokenized_text);
      // Tell the upstream that we changed the type from text to tokenized_text
      $type = "tokenized_text";
    }
    else {
      $value = $this->stripHtmlTagsAndPunctuation($text);
    }
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
  protected function parseTags($yaml_input) {
    try {
      $parser = new Parser();
      $tags = $parser->parse($yaml_input);
      unset($tags['br'], $tags['hr']);
    }
    catch(ParseException $exception) {
      //problem parsing, return empty array
      $tags = FALSE;
    }
    return $tags;
  }
}
