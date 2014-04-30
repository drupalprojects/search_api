<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\Query.
 */

namespace Drupal\search_api\Query;

use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Provides a standard implementation of the QueryInterface.
 */
class Query implements QueryInterface {

  /**
   * The index on which the query will be executed.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The index's ID.
   *
   * Used when serializing, to avoid serializing the index, too.
   *
   * @var string|null
   */
  protected $index_id;

  /**
   * The search keys. If NULL, this will be a filter-only search.
   *
   * @var mixed
   */
  protected $keys;

  /**
   * The unprocessed search keys, as passed to the keys() method.
   *
   * @var mixed
   */
  protected $orig_keys;

  /**
   * The fields that will be searched for the keys.
   *
   * @var array
   */
  protected $fields;

  /**
   * The search filter associated with this query.
   *
   * @var \Drupal\search_api\Query\FilterInterface
   */
  protected $filter;

  /**
   * The sort associated with this query.
   *
   * @var array
   */
  protected $sort;

  /**
   * Search options configuring this query.
   *
   * @var array
   */
  protected $options;

  /**
   * Flag for whether preExecute() was already called for this query.
   *
   * @var bool
   */
  protected $pre_execute = FALSE;

  /**
   * Constructs a new search query.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index the query should be executed on.
   * @param array $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default. This only influences the search keys,
   *     filters will always use AND by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See DefaultQuery::parseModes() for
   *     recognized parse modes.
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *   - 'filter class': Can be used to change the FilterInterface
   *     implementation to use.
   *   - 'search id': A string that will be used as the identifier when storing
   *     this search in the Search API's static cache.
   *   - search_api_access_account: The account which will be used for entity
   *     access checks, if available and enabled for the index.
   *   - search_api_bypass_access: If set to TRUE, entity access checks will be
   *     skipped, even if enabled for the index.
   *   All options are optional. Third-party modules might define and use other
   *   options not listed here.
   *
   * @throws SearchApiException
   *   If a search on that index (or with those options) won't be possible.
   */
  public function __construct(IndexInterface $index, array $options = array()) {
    if (!$index->status()) {
      throw new SearchApiException(t("Can't search on a disabled index."));
    }
    if (isset($options['parse mode'])) {
      $modes = $this->parseModes();
      if (!isset($modes[$options['parse mode']])) {
        throw new SearchApiException(t('Unknown parse mode: @mode.', array('@mode' => $options['parse mode'])));
      }
    }
    $this->index = $index;
    $this->options = $options + array(
      'conjunction' => 'AND',
      'parse mode' => 'terms',
      'filter class' => '\Drupal\search_api\Query\Filter',
      'search id' => __CLASS__,
    );
    $this->filter = $this->createFilter('AND');
    $this->sort = array();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(IndexInterface $index, array $options = array()) {
    return new static($index, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function parseModes() {
    $modes['direct'] = array(
      'name' => t('Direct query'),
      'description' => t("Don't parse the query, just hand it to the search server unaltered. " .
          "Might fail if the query contains syntax errors in regard to the specific server's query syntax."),
    );
    $modes['single'] = array(
      'name' => t('Single term'),
      'description' => t('The query is interpreted as a single keyword, maybe containing spaces or special characters.'),
    );
    $modes['terms'] = array(
      'name' => t('Multiple terms'),
      'description' => t('The query is interpreted as multiple keywords seperated by spaces. ' .
          'Keywords containing spaces may be "quoted". Quoted keywords must still be seperated by spaces.'),
    );
    // @todo Add fourth mode for complicated expressions, e.g.: »"vanilla ice" OR (love NOT hate)«
    return $modes;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseKeys($keys, $mode) {
    if ($keys === NULL || is_array($keys)) {
      return $keys;
    }
    $keys = '' . $keys;
    switch ($mode) {
      case 'direct':
        return $keys;

      case 'single':
        return array('#conjunction' => $this->options['conjunction'], $keys);

      case 'terms':
        $ret = explode(' ', $keys);
        $quoted = FALSE;
        $str = '';
        foreach ($ret as $k => $v) {
          if (!$v) {
            continue;
          }
          if ($quoted) {
            if (substr($v, -1) == '"') {
              $v = substr($v, 0, -1);
              $str .= ' ' . $v;
              $ret[$k] = $str;
              $quoted = FALSE;
            }
            else {
              $str .= ' ' . $v;
              unset($ret[$k]);
            }
          }
          elseif ($v[0] == '"') {
            $len = strlen($v);
            if ($len > 1 && $v[$len-1] == '"') {
              $ret[$k] = substr($v, 1, -1);
            }
            else {
              $str = substr($v, 1);
              $quoted = TRUE;
              unset($ret[$k]);
            }
          }
        }
        if ($quoted) {
          $ret[] = $str;
        }
        $ret['#conjunction'] = $this->options['conjunction'];
        return array_filter($ret);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createFilter($conjunction = 'AND') {
    $filter_class = $this->options['filter class'];
    return new $filter_class($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function keys($keys = NULL) {
    $this->orig_keys = $keys;
    if (isset($keys)) {
      $this->keys = $this->parseKeys($keys, $this->options['parse mode']);
    }
    else {
      $this->keys = NULL;
    }
    return $this;
  }
  /**
   * {@inheritdoc}
   */
  public function fields(array $fields) {
    $fulltext_fields = $this->index->getFulltextFields();
    foreach (array_diff($fields, $fulltext_fields) as $field) {
      throw new SearchApiException(t('Trying to search on field @field which is no indexed fulltext field.', array('@field' => $field)));
    }
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function filter(FilterInterface $filter) {
    $this->filter->filter($filter);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value, $operator = '=') {
    $this->filter->condition($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function sort($field, $order = 'ASC') {
    $fields = $this->index->getOption('fields', array());
    $fields += array(
      'search_api_relevance' => array('type' => 'decimal'),
      'search_api_id' => array('type' => 'integer'),
    );
    if (empty($fields[$field])) {
      throw new SearchApiException(t('Trying to sort on unknown field @field.', array('@field' => $field)));
    }
    $order = strtoupper(trim($order)) == 'DESC' ? 'DESC' : 'ASC';
    $this->sort[$field] = $order;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($offset = NULL, $limit = NULL) {
    $this->options['offset'] = $offset;
    $this->options['limit'] = $limit;
    return $this;
  }


  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Prepare the query for execution by the server.
    $this->preExecute();

    // Execute query.
    $response = $this->index->getServer()->search($this);

    // Postprocess the search results.
    $this->postExecute($response);

    // Store search for later retrieval for facets, etc.
    // @todo Figure out how to store the executed searches for the request.
    // search_api_current_search(NULL, $this, $response);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute() {
    // Make sure to only execute this once per query.
    if (!$this->pre_execute) {
      $this->pre_execute = TRUE;

      // Add fulltext fields, unless set
      if ($this->fields === NULL) {
        $this->fields = $this->index->getFulltextFields();
      }

      // Preprocess query.
      $this->index->preprocessSearchQuery($this);

      // Let modules alter the query.
      \Drupal::moduleHandler()->alter('search_api_query', $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postExecute(array &$results) {
    // Postprocess results.
    $this->index->postprocessSearchResults($results, $this);

    // Let modules alter the results.
    \Drupal::moduleHandler()->alter('search_api_results', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function &getKeys() {
    return $this->keys;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalKeys() {
    return $this->orig_keys;
  }

  /**
   * {@inheritdoc}
   */
  public function &getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter() {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  public function &getSort() {
    return $this->sort;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $value) {
    $old = $this->getOption($name);
    $this->options[$name] = $value;
    return $old;
  }

  /**
   * {@inheritdoc}
   */
  public function &getOptions() {
    return $this->options;
  }

  /**
   * Implements the magic __sleep() method to avoid serializing the index.
   */
  public function __sleep() {
    $this->index_id = $this->index->id();
    $keys = get_object_vars($this);
    unset($keys['index']);
    return array_keys($keys);
  }

  /**
   * Implements the magic __wakeup() method to reload the query's index.
   */
  public function __wakeup() {
    if (!isset($this->index) && !empty($this->index_id)) {
      $this->index = \Drupal::entityManager()->getStorage('search_api_index')->load($this->index_id);
      unset($this->index_id);
    }
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    $ret = 'Index: ' . $this->index->id() . "\n";
    $ret .= 'Keys: ' . str_replace("\n", "\n  ", var_export($this->orig_keys, TRUE)) . "\n";
    if (isset($this->keys)) {
      $ret .= 'Parsed keys: ' . str_replace("\n", "\n  ", var_export($this->keys, TRUE)) . "\n";
      $ret .= 'Searched fields: ' . (isset($this->fields) ? implode(', ', $this->fields) : '[ALL]') . "\n";
    }
    if ($filter = (string) $this->filter) {
      $filter = str_replace("\n", "\n  ", $filter);
      $ret .= "Filters:\n  $filter\n";
    }
    if ($this->sort) {
      $sort = array();
      foreach ($this->sort as $field => $order) {
        $sort[] = "$field $order";
      }
      $ret .= 'Sorting: ' . implode(', ', $sort) . "\n";
    }
    $ret .= 'Options: ' . str_replace("\n", "\n  ", var_export($this->options, TRUE)) . "\n";
    return $ret;
  }

}
