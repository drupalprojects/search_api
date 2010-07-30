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
   * @return
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
   * @param array $results
   *   An array containing the search results. See
   *   SearchApiServiceInterface->search() for the detailled format.
   * @param SearchApiQuery $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$results, SearchApiQuery $query);

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

  protected $index;
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
  public function postprocessSearchResults(array &$results, SearchApiQuery $query) {
    return;
  }

  /**
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
  private function normalizeTokens($tokens, $score = 1) {
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
    if (is_array($value)) {
      foreach ($value as $key => &$v) {
        if (element_child($key)) {
          $this->processKeys($v);
        }
      }
    }
    else {
      $this->processKey($value);
    }
  }

  /**
   * Method for preprocessing query filters. By default does nothing, since
   * filter values are usually unprocessed literals.
   */
  protected function processFilters(array &$filters) {

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
   * Function that is ultimately called for all text by the standard
   * implementation, and does nothing by default.
   *
   * @param $value
   *   The value to preprocess as a string. Can be manipulated directly, nothing
   *   has to be returned. Since this can be called for fields and search keys,
   *   $value should be left a string.
   */
  protected function process(&$value) {

  }

}

/**
 * Processor for tokenizing fulltext data by replacing (configurable)
 * non-letters with spaces.
 */
class SearchApiTokenizer extends SearchApiAbstractFulltextProcessor {

  protected $letters;
  protected $ignorable;

  public function configurationForm() {
    $form = array(
      'letters' => array(
        '#type' => 'textfield',
        '#title' => t('Letter characters'),
        '#description' => t('Specify the characters that should be regarded as letters. ' .
            'The format used is that of <a href="@link">PCRE character classes</a>.',
    array('@link' => url('http://php.net/manual/en/regexp.reference.squarebrackets.php'))),
        '#default_value' => '\p{L}\p{N}',
    ),
      'ignorable' => array(
        '#type' => 'textfield',
        '#title' => t('Ignorable characters'),
        '#description' => t('Specify characters which should be removed from fulltext fields and search strings (e.g., "-"). ' .
            'The same format as above is used.'),
        '#default_value' => '-',
    ),
    );

    if (!empty($this->options)) {
      $form['letters']['#default_value']   = $this->options['letters'];
      $form['ignorable']['#default_value'] = $this->options['ignorable'];
    }

    return $form;
  }

  protected function processFieldValue(&$value) {
    $this->prepare();
    if ($this->ignorable) {
      $value = preg_replace('/[' . $this->ignorable . ']+/u', '', $value);
    }
    if ($this->letters) {
      $arr = preg_split('/[^' . $this->letters . ']+/u', $value);
      if (count($arr) > 1) {
        $value = array();
        foreach ($arr as $token) {
          $value[] = array('value' => $token);
        }
      }
    }
  }

  protected function processKey(&$value) {
    $this->prepare();
    if ($this->ignorable) {
      $value = preg_replace('/[' . $this->ignorable . ']+/u', '', $value);
    }
    if ($this->letters) {
      $value = preg_replace('/[^' . $this->letters . ']+/u', ' ', $value);
    }
  }

  protected function prepare() {
    if (!isset($this->letters)) {
      $this->letters = str_replace('/', '\/', $this->options['letters']);
      $this->ignorable = str_replace('/', '\/', $this->options['ignorable']);
    }
  }

}

/**
 * Processor for making searches case-independent.
 */
class SearchApiIgnoreCase extends SearchApiAbstractFulltextProcessor {

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

/**
 * Processor for ignoring the case of fulltext (and possibly string) fields.
 */
// @todo Implement
class SearchApiHtmlFilter extends SearchApiAbstractFulltextProcessor {

  public function configurationForm() {

  }

  protected function process(&$value) {

  }

}
