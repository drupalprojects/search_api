<?php
// $Id$

/**
 * Interface representing a search query on an Search API index.
 *
 * Methods not returning something else will return the object itself, so calls
 * can be chained.
 */
interface SearchApiQueryInterface {

  /**
   * Constructor used when creating SearchApiQueryInterface objects.
   *
   * @param SearchApiIndex $index
   *   The index the query should be executed on.
   * @param $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See SearchApiQuery::parseModes() for
   *     recognized parse modes.
   *   - languages: The languages to search for.
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   */
  public function __construct(SearchApiIndex $index, $options = array());

  /**
   * @return
   *   An associative array of parse modes recognized by objects of this class.
   *   The keys are the parse modes' ids, values are associative arrays
   *   containing the following entries:
   *   - name: The translated name of the parse mode.
   *   - description: (optional) A translated text describing the parse mode.
   */
  public function parseModes();

  /**
   * Sets the keys to search for. If this method is not called on the query
   * before execution, this will be a filter-only query.
   *
   * @param $keys
   *   A string with the unparsed search keys, or NULL to use no search keys.
   */
  public function keys($keys = NULL);

  /**
   * Sets the fields that will be searched for the search keys. If this is not
   * called, all fulltext fields should be searched.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   */
  public function fields(array $fields);

  /**
   * Adds a subfilter to this query's filter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function filter($filter);

  /**
   * Add a new ($field $operator $value) condition filter.
   *
   * @param $field
   *   The field to filter on, e.g. 'title'.
   * @param $value
   *   The value the field should have (or be related to by the operator).
   * @param $operator
   *   The operator to use for checking the constraint, e.g. '=' or '<'.
   */
  public function condition($field, $value, $operator = '=');

  /**
   * Add a sort directive to this search query.
   *
   * @param $field
   *   The field to sort by. The special field 'search_api_relevance' may be
   *   used to sort by relevance.
   * @param $order
   *   The order to sort items in - either 'ASC' or 'DESC'.
   */
  public function sort($field, $order = 'ASC');

  /**
   * Executes this search query.
   *
   * @return
   *   An associative array containing the search results and some metadata.
   */
  //@todo Specify this in a more detailled way.
  public function execute();

  /**
   * @return
   *   The search index this query should be executed on.
   */
  public function getIndex();

  /**
   * @return
   *   This object's search keys - either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   */
  public function &getKeys();

  /**
   * @return
   *   An array containing the fields that should be searched for the search
   *   keys. If this is NULL, all fulltext fields should be searched.
   */
  public function &getFields();

  /**
   * @return
   *   This object's associated SearchApiQueryFilter object.
   */
  public function &getFilter();

  /**
   * @return
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   */
  public function &getSort();

  /**
   * @return
   *   An associative array of query options.
   */
  public function &getOptions();

}

/**
 * Standard implementation of SearchApiQueryInterface.
 */
class SearchApiQuery implements SearchApiQueryInterface {

  /** The index. */
  protected $index;

  /** The search keys. If NULL, this will be a filter-only search. */
  protected $keys;

  /** The fields that will be searched for the keys. */
  protected $fields;

  /** The search filter associated with this query. */
  protected $filter;

  /** The sort associated with this query. */
  protected $sort;

  /** Search options configuring this query. */
  protected $options;

  /**
   * Constructor for SearchApiQuery objects.
   *
   * @param SearchApiIndex $index
   *   The index the query should be executed on.
   * @param $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See SearchApiQuery::parseModes() for
   *     recognized parse modes.
   *   - languages: The languages to search for.
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   */
  public function __construct(SearchApiIndex $index, $options = array()) {
    $this->index = $index;
    $this->options = $options + array(
      'conjunction' => 'AND',
      'parse mode' => 'terms',
      'filter class' => 'SearchApiQueryFilter',
    );
    $this->filter = $this->createFilter($this->options['conjunction']);
    $this->sort = array();
  }

  /**
   * @return
   *   An associative array of parse modes recognized by objects of this class.
   *   The keys are the parse modes' ids, values are associative arrays
   *   containing the following entries:
   *   - name: The translated name of the parse mode.
   *   - description: (optional) A translated text describing the parse mode.
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
    // @todo Add fourth mode for complicated expr., e.g.: "vanilla ice" (love OR NOT hate)
    return $modes;
  }

  /**
   * Parses the keys string according to the $mode parameter.
   *
   * @return
   *   The parsed keys. Either a string or an array.
   */
  protected function parseKeys($keys, $mode) {
    if ($keys == NULL || is_array($keys)) {
      return $keys;
    }
    $keys = '' . $keys;
    switch ($mode) {
      case 'direct':
        return $keys;

      case 'single':
        return array($keys);

      case 'terms':
        $ret = explode(' ', $keys);
        $quoted = FALSE;
        $str = '';
        foreach ($ret as $k => $v) {
          if (!$v) {
            continue;
          }
          if ($quoted) {
            if ($v[strlen($v)-1] == '"') {
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
        return array_filter($ret);
    }
  }

  /**
   * @param $conjunction
   *   The conjunction to use for the filter - either 'AND' or 'OR'.
   *
   * @return
   *   An object that implements SearchApiQueryFilterInterface and is set to use
   *   the specified conjunction.
   */
  protected function createFilter($conjunction = 'AND') {
    $filter_class = $this->options['filter class'];
    return new $filter_class($conjunction);
  }

  /**
   * Sets the keys to search for. If this method is not called on the query
   * before execution, this will be a filter-only query.
   *
   * @param $keys
   *   A string with the unparsed search keys, or NULL to use no search keys.
   *
   * @return
   *   The called object.
   */
  public function keys($keys = NULL) {
    if (isset($keys)) {
      $this->keys = $this->parseKeys($keys, $this->options['parse mode']);
    }
    else {
      $this->keys = NULL;
    }
    return $this;
  }

  /**
   * Sets the fields that will be searched for the search keys.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   */
  public function fields(array $fields) {
    $this->fields = $fields;
    return $this;
  }

  /**
   * Adds a subfilter to this query's filter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   *
   * @return
   *   The called object.
   */
  public function filter($filter) {
    $this->filter->filter($filter);
    return $this;
  }

  /**
   * Add a new ($field $operator $value) condition filter.
   *
   * @param $field
   *   The field to filter on, e.g. 'title'.
   * @param $value
   *   The value the field should have (or be related to by the operator).
   * @param $operator
   *   The operator to use for checking the constraint, e.g. '=' or '<'.
   *
   * @return
   *   The called object.
   */
  public function condition($field, $value, $operator = '=') {
    $this->filter->condition($field, $value, $operator);
    return $this;
  }

  /**
   * Add a sort directive to this search query.
   *
   * @param $field
   *   The field to sort by. The special field 'search_api_relevance' may be
   *   used to sort by relevance.
   * @param $order
   *   The order to sort items in - either 'ASC' or 'DESC'.
   *
   * @return
   *   The called object.
   */
  public function sort($field, $order = 'ASC') {
    $order = strtoupper(trim($order)) == 'DESC' ? 'DESC' : 'ASC';
    $this->sort[$field] = $order;
    return $this;
  }

  /**
   * Executes this search query.
   *
   * Since this method takes care of important hook invocations, caching, etc.,
   * it must not be overridden. Override the provided hooks preExecute() and
   * postExecute() if you have to add/modify behaviour.
   */
  public final function execute() {
    // Call pre-execute hook.
    $this->preExecute();

    // Preprocess query
    $this->index->preprocessSearchQuery($this);

    // Let modules alter the query
    drupal_alter('search_api_query', $this);

    // Cache query (e.g. for facets)
    $query_cache = &drupal_static('search_api_search_query');
    $query_cache[] = $this;

    // Execute query
    $results = $server->search($this);

    // Call post-execute hook.
    $this->postExecute($results);

    // Postprocess results
    $this->index->postprocessSearchResults($results, $this);

    // Cache results
    $result_cache = &drupal_static('search_api_search_results');
    $result_cache[] = $results;

    return $results;
  }

  /**
   * Pre-execute hook for modifying search behaviour.
   */
  protected function preExecute() {}

  /**
   * Post-execute hook for modifying search behaviour.
   *
   * @param array $results
   *   The results returned by the server, which may be altered.
   */
  protected function postExecute(array &$results) {}

  /**
   * @return
   *   The search index this query will be executed on.
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * @return
   *   This object's search keys - either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   */
  public function &getKeys() {
    return $this->keys;
  }

  /**
   * @return
   *   An array containing the fields that should be searched for the search
   *   keys.
   */
  public function &getFields() {
    return $this->fields;
  }

  /**
   * @return
   *   This object's associated SearchApiQueryFilter object.
   */
  public function &getFilter() {
    return $this->filter;
  }

  /**
   * @return
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   */
  public function &getSort() {
    return $this->sort;
  }

  /**
   * @return
   *   An associative array of query options.
   */
  public function &getOptions() {
    return $this->options;
  }

}

/**
 * Interface representing a search query filter, that filters on one or more
 * fields with a specific conjunction (AND or OR).
 *
 * Methods not noting otherwise will return the object itself, so calls can be
 * chained.
 */
interface SearchApiQueryFilterInterface {

  /**
   * Constructs a new filter that uses the specified conjunction.
   *
   * @param $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function __construct($conjunction = 'AND');

  /**
   * Sets this filter's conjunction.
   *
   * @param $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function setConjunction($conjunction);

  /**
   * Adds a subfilter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function filter(SearchApiQueryFilter $filter);

  /**
   * Add a new ($field $operator $value) condition.
   *
   * @param $field
   *   The field to filter on, e.g. 'title'.
   * @param $value
   *   The value the field should have (or be related to by the operator).
   * @param $operator
   *   The operator to use for checking the constraint, e.g. '=' or '<'.
   */
  public function condition($field, $value, $operator = '=');

  /**
   * @return
   *   The conjunction used by this filter - either 'AND' or 'OR'.
   */
  public function getConjunction();

  /**
   * @return
   *   An array containing this filter's subfilters. Each of these is either an
   *   array (field, value, operator), or another SearchApiFilter object.
   */
  public function &getFilters();

}

/**
 * Standard implementation of SearchApiQueryFilterInterface.
 */
class SearchApiQueryFilter implements SearchApiQueryFilterInterface {

  /**
   * Array containing subfilters. Each of these is either an array
   * (field, value, operator), or another SearchApiFilter object.
   */
  protected $filters;

  /** String specifying this filter's conjunction ('AND' or 'OR'). */
  protected $conjunction;

  /**
   * Constructs a new filter that uses the specified conjunction.
   *
   * @param $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function __construct($conjunction = 'AND') {
    $this->setConjunction($conjunction);
    $this->filters = array();
  }

  /**
   * Sets this filter's conjunction.
   *
   * @param $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function setConjunction($conjunction) {
    $this->conjunction = strtoupper(trim($conjunction)) == 'OR' ? 'OR' : 'AND';
    return $this;
  }

  /**
   * Adds a subfilter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function filter(SearchApiQueryFilter $filter) {
    $this->filters[] = $filter;
    return $this;
  }

  /**
   * Add a new ($field $operator $value) condition.
   *
   * @param $field
   *   The field to filter on, e.g. 'title'.
   * @param $value
   *   The value the field should have (or be related to by the operator).
   * @param $operator
   *   The operator to use for checking the constraint, e.g. '=' or '<'.
   */
  public function condition($field, $value, $operator = '=') {
    $this->filters[] = array($field, $value, $operator);
    return $this;
  }

  /**
   * @return
   *   The conjunction used by this filter - either 'AND' or 'OR'.
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * @return
   *   An array containing this filter's subfilters. Each of these is either an
   *   array (field, value, operator), or another SearchApiFilter object.
   */
  public function &getFilters() {
    return $this->filters;
  }

}
