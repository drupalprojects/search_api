<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

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
      'tags' => <<<DEFAULT_TAGS
h1: 5
h2: 3
h3: 2
string: 2
b: 2
em: 1.5
u: 1.5
DEFAULT_TAGS
    );

    $this->tags = $this->parseTags($this->options['tags']);
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
    catch(\Symfony\Component\Yaml\Exception\ParseException $exception) {
      //problem parsing, return empty array
      $tags = FALSE;
    }

    return $tags;

  }

  /**
   * Builds configuration form
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

    $form['tags'] = array(
      '#type' => 'textarea',
      '#title' => t('Tag boosts'),
      '#description' => t('Specify special boost values for certain HTML elements, in <a href="@link">YAML file format</a>. ' .
          'The boost values of nested elements are multiplied, elements not mentioned will have the default boost value of 1. ' .
          'Assign a boost of 0 to ignore the text content of that HTML element.',
          array('@link' => url('https://api.drupal.org/api/drupal/core!vendor!symfony!yaml!Symfony!Component!Yaml!Yaml.php/function/Yaml::parse/8'))),
      '#default_value' => $this->configuration['tags'],
    );

    return $form;

  }

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

  protected function processFieldValue(&$value) {
    $text = str_replace(array('<', '>'), array(' <', '> '), $value); // Let removed tags still delimit words.
    if ($this->configuration['title']) {
      $text = preg_replace('/(<[-a-z_]+[^>]+)\btitle\s*=\s*("([^"]+)"|\'([^\']+)\')([^>]*>)/i', '$1 $5 $3$4 ', $text);
    }
    if ($this->configuration['alt']) {
      $text = preg_replace('/<img\b[^>]+\balt\s*=\s*("([^"]+)"|\'([^\']+)\')[^>]*>/i', ' <img>$2$3</img> ', $text);
    }
    if ($this->configuration) {
      $text = strip_tags($text, '<' . implode('><', array_keys($this->tags)) . '>');
      $value = $this->parseText($text);
    }
    else {
      $value = strip_tags($text);
    }
  }

  protected function parseText(&$text, $active_tag = NULL, $boost = 1) {
    $ret = array();
    while (($pos = strpos($text, '<')) !== FALSE) {
      if ($boost && $pos > 0) {
        $ret[] = array(
          'value' => html_entity_decode(substr($text, 0, $pos), ENT_QUOTES, 'UTF-8'),
          'score' => $boost,
        );
      }
      $text = substr($text, $pos + 1);
      preg_match('#^(/?)([-:_a-zA-Z]+)#', $text, $m);
      $text = substr($text, strpos($text, '>') + 1);
      if ($m[1]) {
        // Closing tag.
        if ($active_tag && $m[2] == $active_tag) {
          return $ret;
        }
      }
      else {
        // Opening tag => recursive call.
        $inner_boost = $boost * (isset($this->tags[$m[2]]) ? $this->tags[$m[2]] : 1);
        $ret = array_merge($ret, $this->parseText($text, $m[2], $inner_boost));
      }
    }
    if ($text) {
      $ret[] = array(
        'value' => html_entity_decode($text, ENT_QUOTES, 'UTF-8'),
        'score' => $boost,
      );
      $text = '';
    }
    return $ret;
  }

}
