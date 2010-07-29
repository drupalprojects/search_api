<?php
// $Id$

/**
 * Interface representing a Search API pre- and/or post-processor.
 *
 * Enabling classes for preprocessing and postprocessing is done seperately.
 * However, an enabled index data preprocessor will also be used to preprocess
 * search queries on that index, since most of the time the same steps should be
 * executed in both phases. If this does not apply to a specific processor, the
 * unnecessary method can be left blank.
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
   * Preprocess data items for indexing. Data added by data alter callbacks will
   * be available on the items.
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
 * Abstract processor implementation that can be used for processors that work
 * on fulltext data.
 */
abstract class SearchApiAbstractFulltextProcessor implements SearchApiProcessorInterface {

  protected $index;
  protected $options;

  public function __construct(SearchApiIndex $index, array $options = array()) {
    $this->index   = $index;
    $this->options = $options;
  }

  public function configurationForm() { }

  /**
   * Calls processAll() for all fields with an appropriate type.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as &$item) {
      foreach ($item as &$field) {
        if ($this->testType($field['type'])) {
          $this->processAll($field['value']);
        }
      }
    }
  }

  /**
   * @return
   *   TRUE, iff the type should be processed.
   */
  protected function testType($type) {
    return $type == 'text' || $type == 'list<text>';
  }

  /**
   * Calls processAll() for the keys and filters on all fields with an
   * appropriate type.
   */
  public function preprocessSearchQuery(SearchApiQuery $query) {
    $keys = &$query->getKeys();
    $this->processAll($keys);
    $filter = &$query->getFilter();
    $filters = &$filter->getFilters();
    $fields = $this->index['options']['fields'];
    $this->processFilters($filters, $fields);
  }

  protected function processFilters(&$filters, $fields) {
    foreach ($filters as &$f) {
      if (is_array($f)) {
        if (isset($fields[$f[0]]['type']) && $this->testType($fields[$f[0]]['type'])) {
          $this->processAll($f[1]);
        }
      }
      else {
        $child_filters = &$f->getFilters();
        $this->processFilters($child_filters);
      }
    }
  }

  /**
   * Does nothing.
   */
  public function postprocessSearchResults(array &$results, SearchApiQuery $query) {
    return;
  }

  /**
   * Calls process() either directly, or recursively for all children -
   * depending on whether $value is an array or not.
   */
  protected function processAll(&$value) {
    if (is_array($value)) {
      foreach ($value as $key => &$v) {
        if (element_child($key)) {
          $this->processAll($v);
        }
      }
    }
    else {
      $this->process($value);
    }
  }

  /**
   * Function that does the real processing. Has to be overridden by concrete
   * processors. $value can be manipulated directly, nothing has to be returned.
   */
  protected abstract function process(&$value);

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

  protected function process(&$value) {
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
    if (parent::testType($type)) {
      return TRUE;
    }
    else if (!empty($this->options['strings'])) {
      return $type == 'string' || $type == 'list<string>';
    }
    return FALSE;
  }

  protected function process(&$value) {
    $value = strtolower($value);
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
