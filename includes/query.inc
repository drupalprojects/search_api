<?php
// $Id$

/**
 * Interface representing a search query on an Search API index.
 *
 * Methods not noting otherwise will return the object itself, so calls can be
 * chained.
 */
interface SearchApiQueryInterface {

  /**
   * Constructor used for SearchApiQueryInterface objects.
   *
   * @param $keys
   *   The search keys.
   * @param $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See SearchApiQuery::parseModes() for
   *     recognized parse modes.
   */
  public function __construct($keys = NULL, $options = array());

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
   * Adds a subfilter to this query's filter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function addFilter($filter);

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
  public function addCondition($field, $value, $operator = '=');

  /**
   * Add a sort directive to this search query.
   *
   * @param $field
   *   The field to sort by. The special field 'search_api_relevance' may be
   *   used to sort by relevance.
   * @param $order
   *   The order to sort items in - either 'ASC' or 'DESC'.
   */
  public function addSort($field, $order = 'ASC');

  /**
   * @return
   *   This object's search keys - either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   */
  public function &keys();

  /**
   * @return
   *   This object's associated SearchApiQueryFilter object.
   */
  public function &filter();

  /**
   * @return
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   */
  public function &sort();

  /**
   * @return
   *   An associative array of query options.
   */
  public function &options();

}

/**
 * Standard implementation of SearchApiQueryInterface.
 */
class SearchApiQuery {

  /** The search keys. If NULL, this will be a filter-only search. */
  protected $keys;

  /** The search filter associated with this query. */
  protected $filter;

  /** The sort associated with this query. */
  protected $sort;

  /** Search options configuring this query. */
  protected $options;

  /**
   * Constructor for a SearchApiQuery object.
   *
   * @param $keys
   *   The search keys.
   * @param $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See SearchApiQuery::parseModes() for
   *     recognized parse modes.
   *   - 'filter class': A class implementing SearchApiQueryFilterInterface that
   *     should be used for filters in this query.
   */
  public function __construct($keys = NULL, $options = array()) {
    $this->options = $options + array('conjunction' => 'AND',
        'parse mode' => 'terms', 'filter class' => 'SearchApiQueryFilter');
    $this->keys = $this->parseKeys($keys, $this->options['parse mode']);
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
      'description' => t("Don't parse the query, just hand it to the search " .
          'server unaltered. Might fail if the query contains syntax errors ' .
          "in regard to the specific server's query syntax."),
    );
    $modes['single'] = array(
      'name' => t('Single term'),
      'description' => t('The query is interpreted as a single keyword, ' .
          'maybe containing spaces or special characters.'),
    );
    $modes['terms'] = array(
      'name' => t('Multiple terms'),
      'description' => t('The query is interpreted as multiple keywords ' .
          'seperated by spaces. Keywords containing spaces may be "quoted". ' .
          'Quoted keywords must still be seperated by spaces.'),
    );
    // @todo Add fourth mode for complicated expr., e.g.:
    // "vanilla ice" (love OR NOT hate)
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
          else if ($v[0] == '"') {
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
   * Adds a subfilter to this query's filter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function addFilter($filter) {
    $this->filter->addFilter($filter);
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
   */
  public function addCondition($field, $value, $operator = '=') {
    $this->filter->addCondition($field, $value, $operator);
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
   */
  public function addSort($field, $order = 'ASC') {
    $order = strtoupper(trim($order)) == 'DESC' ? 'DESC' : 'ASC';
    $this->sort[$field] = $order;
    return $this;
  }

  /**
   * @return
   *   This object's search keys - either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   */
  public function &keys() {
    return $this->keys;
  }

  /**
   * @return
   *   This object's associated SearchApiQueryFilter object.
   */
  public function &filter() {
    return $this->filter;
  }

  /**
   * @return
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   */
  public function &sort() {
    return $this->sort;
  }

  /**
   * @return
   *   An associative array of query options.
   */
  public function &options() {
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
   * Adds a subfilter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function addFilter(SearchApiQueryFilter $filter);

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
  public function addCondition($field, $value, $operator = '=');

  /**
   * Sets this filter's conjunction.
   *
   * @param $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function setConjunction($conjunction);

  /**
   * @return
   *   The conjunction used by this filter - either 'AND' or 'OR'.
   */
  public function conjunction();

  /**
   * @return
   *   An array containing this filter's subfilters. Each of these is either an
   *   array (field, value, operator), or another SearchApiFilter object.
   */
  public function &filters();

}


/**
 * Standard implementation of SearchApiQueryFilterInterface.
 */
class SearchApiQueryFilter {

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
   * Adds a subfilter.
   *
   * @param $filter
   *   A SearchApiQueryFilter object that should be added as a subfilter.
   */
  public function addFilter(SearchApiQueryFilter $filter) {
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
  public function addCondition($field, $value, $operator = '=') {
    $this->filters[] = array($field, $value, $operator);
    return $this;
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
   * @return
   *   The conjunction used by this filter - either 'AND' or 'OR'.
   */
  public function conjunction() {
    return $this->conjunction;
  }

  /**
   * @return
   *   An array containing this filter's subfilters. Each of these is either an
   *   array (field, value, operator), or another SearchApiFilter object.
   */
  public function &filters() {
    return $this->filters;
  }

}
