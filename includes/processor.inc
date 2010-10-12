<?php
// $Id$

/**
 * Interface representing a Search API pre- and/or post-processor.
 *
 * Enabling classes for preprocessing and postprocessing is done seperately.
 * However, an enabled index data preprocessor will also be used to preprocess
 * search queries on that index, since most of the time the same steps should be
 * executed in both phases. If this does not apply to a specific processor, the
 * unnecessary method(s) can be left blank.
 */
interface SearchApiProcessorInterface {

  /**
   * Construct a processor.
   *
   * @param SearchApiIndex $index
   *   The index for which processing is done.
   * @param array $options
   *   The processor options set for this index.
   */
  public function __construct(SearchApiIndex $index, array $options = array());

  /**
   * Display a form for configuring this processor.
   * Since forcing users to specify options for disabled processors makes no
   * sense, none of the form elements should have the '#required' attribute set.
   *
   * @return array
   *   A form array for configuring this processor, or FALSE if no configuration
   *   is possible.
   */
  public function configurationForm();

  /**
   * Preprocess data items for indexing.
   *
   * Typically, a preprocessor will execute its preprocessing (e.g. stemming,
   * n-grams, word splitting, stripping stop words, etc.) only on the items'
   * search_api_fulltext fields, if set. Other fields should usually be left
   * untouched.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing, formatted as specified
   *   by SearchApiServiceInterface::indexItems().
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param SearchApiQuery $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(SearchApiQuery $query);

  /**
   * Postprocess search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results. See the return value of
   *   SearchApiQueryInterface->execute() for the detailled format.
   * @param SearchApiQuery $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, SearchApiQuery $query);

}

/**
 * Abstract processor implementation that provides an easy framework for only
 * processing specific fields.
 *
 * Simple processors can just override process(), while others might want to
 * override the other process*() methods, and test*() (for restricting
 * processing to something other than all fulltext data).
 */
abstract class SearchApiAbstractProcessor implements SearchApiProcessorInterface {

  /**
   * @var SearchApiIndex
   */
  protected $index;

  /**
   * @var array
   */
  protected $options;

  /**
   * Constructor, saving its arguments into properties.
   */
  public function __construct(SearchApiIndex $index, array $options = array()) {
    $this->index   = $index;
    $this->options = $options;
  }

  public function configurationForm() { }

  /**
   * Calls processField() for all appropriate fields.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as &$item) {
      foreach ($item as $name => &$field) {
        if ($this->testField($name, $field)) {
          $this->processField($field['value'], $field['type']);
        }
      }
    }
  }

  /**
   * Calls processKeys() for the keys and processFilters() for the filters.
   */
  public function preprocessSearchQuery(SearchApiQuery $query) {
    $keys = &$query->getKeys();
    $this->processKeys($keys);
    $filter = &$query->getFilter();
    $filters = &$filter->getFilters();
    $this->processFilters($filters);
  }

  /**
   * Does nothing.
   */
  public function postprocessSearchResults(array &$response, SearchApiQuery $query) {
    return;
  }

  /**
   * Method for preprocessing field data.
   *
   * Calls process() either for the whole text, or each token, depending on the
   * type. Also takes care of extracting list values and of fusing returned
   * tokens back into a one-dimensional array.
   */
  protected function processField(&$value, &$type) {
    if (substr($type, 0, 5) == 'list<') {
      $type = substr($type, 5, -1);
      foreach ($value as &$v) {
        $this->processField($v, $type);
      }
      $type = "list<$type>";
      return;
    }
    if ($type == 'tokens') {
      foreach ($value as &$token) {
        $this->processFieldValue($token['value']);
      }
    }
    else {
      $this->processFieldValue($value);
    }
    if (is_array($value)) {
      $type = 'tokens';
      $value = $this->normalizeTokens($value);
    }
  }

  /**
   * Internal helper function for normalizing tokens.
   */
  protected function normalizeTokens($tokens, $score = 1) {
    $ret = array();
    foreach ($tokens as $token) {
      if (empty($token['value'])) {
        // Filter out empty tokens.
        continue;
      }
      if (!isset($token['score'])) {
        $token['score'] = $score;
      }
      else {
        $token['score'] *= $score;
      }
      if (is_array($token['value'])) {
        foreach ($this->normalizeTokens($token['value'], $token['score']) as $t) {
          $ret[] = $t;
        }
      }
      else {
        $ret[] = $token;
      }
    }
    return $ret;
  }

  /**
   * Method for preprocessing search keys.
   */
  protected function processKeys(&$keys) {
    if (is_array($keys)) {
      foreach ($keys as $key => &$v) {
        if (element_child($key)) {
          $this->processKeys($v);
        }
      }
    }
    else {
      $this->processKey($keys);
    }
  }

  /**
   * Method for preprocessing query filters.
   */
  protected function processFilters(array &$filters) {
    $fields = $this->index->options['fields'];
    foreach ($filters as &$f) {
      if (is_array($f)) {
        if (isset($fields[$f[0]]) && $this->testField($f[0], $fields[$f[0]])) {
          $this->processFilterValue($f[1]);
        }
      }
      else {
        $child_filters = &$f->getFilters();
        $this->processFilters($child_filters);
      }
    }
  }

  /**
   * @param $name
   *   The field's machine name.
   * @param array $field
   *   The field's information.
   *
   * @return
   *   TRUE, iff the field should be processed.
   */
  protected function testField($name, array $field) {
    return $this->testType($field['type']);
  }

  /**
   * @return
   *   TRUE, iff the type should be processed.
   */
  protected function testType($type) {
    return search_api_is_text_type($type, array('text', 'tokens'));
  }

  /**
   * Called for processing a single text element in a field. The default
   * implementation just calls process().
   *
   * $value can either be left a string, or changed into an array of tokens. A
   * token is an associative array containing:
   * - value: Either the text inside the token, or a nested array of tokens. The
   *   score of nested tokens will be multiplied by their parent's score.
   * - score: The relative importance of the token, as a float, with 1 being
   *   the default.
   */
  protected function processFieldValue(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single search keyword. The default implementation
   * just calls process().
   *
   * $value can either be left a string, or be changed into a nested keys array,
   * as defined by SearchApiQueryInterface.
   */
  protected function processKey(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single filter value. The default implementation
   * just calls process().
   *
   * $value has to remain a string.
   */
  protected function processFilterValue(&$value) {
    $this->process($value);
  }

  /**
   * Function that is ultimately called for all text by the standard
   * implementation, and does nothing by default.
   *
   * @param $value
   *   The value to preprocess as a string. Can be manipulated directly, nothing
   *   has to be returned. Since this can be called for all value types, $value
   *   has to remain a string.
   */
  protected function process(&$value) {

  }

}

/**
 * Processor for tokenizing fulltext data by replacing (configurable)
 * non-letters with spaces.
 */
class SearchApiTokenizer extends SearchApiAbstractProcessor {

  /**
   * @var string
   */
  protected $spaces;

  /**
   * @var string
   */
  protected $ignorable;

  public function configurationForm() {
    $form = array(
      'spaces' => array(
        '#type' => 'textfield',
        '#title' => t('Whitespace characters'),
        '#description' => t('Specify the characters that should be regarded as whitespace and therefore used as word-delimiters. ' .
            'Specify the characters as a <a href="@link">PCRE character class</a>.',
            array('@link' => url('http://www.php.net/manual/en/regexp.reference.character-classes.php'))),
        '#default_value' => '[^\p{L}\p{N}]',
      ),
      'ignorable' => array(
        '#type' => 'textfield',
        '#title' => t('Ignorable characters'),
        '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). ' .
            'The same format as above is used.'),
        '#default_value' => '[-]',
      ),
    );

    if (!empty($this->options)) {
      $form['spaces']['#default_value']   = $this->options['spaces'];
      $form['ignorable']['#default_value'] = $this->options['ignorable'];
    }

    return $form;
  }

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

  protected function process(&$value) {
    $this->prepare();
    if ($this->ignorable) {
      $value = preg_replace('/' . $this->ignorable . '+/u', '', $value);
    }
    if ($this->spaces) {
      $value = preg_replace('/' . $this->spaces . '+/u', ' ', $value);
    }
  }

  protected function prepare() {
    if (!isset($this->spaces)) {
      $this->spaces = str_replace('/', '\/', $this->options['spaces']);
      $this->ignorable = str_replace('/', '\/', $this->options['ignorable']);
    }
  }

}

/**
 * Processor for stripping HTML from indexed fulltext data. Supports assigning
 * custom boosts for any HTML element.
 */
// @todo Process query?
class SearchApiHtmlFilter extends SearchApiAbstractProcessor {

  /**
   * @var array
   */
  protected $tags;

  public function __construct(SearchApiIndex $index, array $options = array()) {
    parent::__construct($index, $options);
    $this->options += array(
      'title' => FALSE,
      'alt'   => TRUE,
      'tags'  => "h1 = 5\n" .
          "h2 = 3\n" .
          "h3 = 2\n" .
          "strong = 2\n" .
          "b = 2\n" .
          "em = 1.5\n" .
          'u = 1.5',
    );
    $this->tags = drupal_parse_info_format($this->options['tags']);
    // Specifying empty tags doesn't make sense.
    unset($this->tags['img'], $this->tags['br'], $this->tags['hr']);
  }

  public function configurationForm() {
    $form = array(
      'title' => array(
        '#type' => 'checkbox',
        '#title' => t('Index title attribute'),
        '#description' => t('If set, the contents of title attributes will be indexed.'),
        '#default_value' => $this->options['title'],
      ),
      'alt' => array(
        '#type' => 'checkbox',
        '#title' => t('Index alt attribute'),
        '#description' => t('If set, the alternative text y.'),
        '#default_value' => $this->options['alt'],
      ),
      'tags' => array(
        '#type' => 'textarea',
        '#title' => t('Tag boosts'),
        '#description' => t('Specify special boost values for certain HTML elements, in <a href="@link">INI file format</a>. ' .
            'The boost values of nested elements are multiplied, elements not mentioned will have the default boost value of 1. ' .
            'Assign a boost of 0 to ignore the text content of that HTML element.',
            array('@link' => url('http://api.drupal.org/api/function/drupal_parse_info_format/7'))),
        '#default_value' => $this->options['tags'],
      ),
    );

    return $form;
  }

  protected function processFieldValue(&$value) {
    $text = str_replace(array('<', '>'), array(' <', '> '), $value); // Let removed tags still delimit words.
    if ($this->options['title']) {
      $text = preg_replace('/(<[-a-z_]+[^>]+)\btitle\s*=\s*("([^"]+)"|\'([^\']+)\')([^>]*>)/i', '$1 $5 $3$4 ', $text);
    }
    if ($this->options['alt']) {
      $text = preg_replace('/<img\b[^>]+\balt\s*=\s*("([^"]+)"|\'([^\']+)\')[^>]*>/i', ' $2$3 ', $text);
    }
    if ($this->tags) {
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

/**
 * Processor for making searches case-insensitive.
 */
class SearchApiIgnoreCase extends SearchApiAbstractProcessor {

  public function configurationForm() {
    $form = array(
      'strings' => array(
        '#type' => 'checkbox',
        '#title' => t('Process strings'),
        '#description' => t('Determines whether this processor will process string fields, too.'),
      ),
    );

    if (!empty($this->options)) {
      $form['strings']['#default_value']   = $this->options['strings'];
    }

    return $form;
  }

  public function testType($type) {
    $allowed = array('text', 'tokens');
    if (!empty($this->options['strings'])) {
      $allowed[] = 'string';
    }
    return search_api_is_text_type($type, $allowed);;
  }

  protected function process(&$value) {
    $value = drupal_strtolower($value);
  }

}
