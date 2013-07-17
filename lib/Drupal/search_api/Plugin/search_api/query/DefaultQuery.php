<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\query\DefaultQuery.
 */

namespace Drupal\search_api\Plugin\search_api\query;

/**
 * Provides a standard implementation of the QueryInterface.
 */
class DefaultQuery implements QueryInterface {

  /**
   * The index.
   *
   * @var Index
   */
  protected $index;

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
   * @var FilterInterface
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
   * {@inheritdoc}
   */
  public function __construct(Index $index, array $options = array()) {
    if (empty($index->options['fields'])) {
      throw new SearchApiException(t("Can't search an index which hasn't got any fields defined."));
    }
    if (empty($index->enabled)) {
      throw new SearchApiException(t("Can't search a disabled index."));
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
      'filter class' => 'DefaultFilter',
      'search id' => __CLASS__,
    );
    $this->filter = $this->createFilter('AND');
    $this->sort = array();
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
    $fields = $this->index->options['fields'];
    $fields += array(
      'search_api_relevance' => array('type' => 'decimal'),
      'search_api_id' => array('type' => 'integer'),
    );
    if (empty($fields[$field])) {
      throw new SearchApiException(t('Trying to sort on unknown field @field.', array('@field' => $field)));
    }
    $type = $fields[$field]['type'];
    if (search_api_is_list_type($type) || search_api_is_text_type($type)) {
      throw new SearchApiException(t('Trying to sort on field @field of illegal type @type.', array('@field' => $field, '@type' => $type)));
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
    $start = microtime(TRUE);

    // Prepare the query for execution by the server.
    $this->preExecute();

    $pre_search = microtime(TRUE);

    // Execute query.
    $response = $this->index->server()->search($this);

    $post_search = microtime(TRUE);

    // Postprocess the search results.
    $this->postExecute($response);

    $end = microtime(TRUE);
    $response['performance']['complete'] = $end - $start;
    $response['performance']['hooks'] = $response['performance']['complete'] - ($post_search - $pre_search);

    // Store search for later retrieval for facets, etc.
    search_api_current_search(NULL, $this, $response);

    return $response;
  }

  /**
   * Adds language filters for the query.
   *
   * Internal helper function.
   *
   * @param array $languages
   *   The languages for which results should be returned.
   */
  protected function addLanguages(array $languages) {
    if (array_search(LANGUAGE_NONE, $languages) === FALSE) {
      $languages[] = LANGUAGE_NONE;
    }

    $languages = drupal_map_assoc($languages);
    $langs_to_add = $languages;
    $filters = $this->filter->getFilters();
    while ($filters && $langs_to_add) {
      $filter = array_shift($filters);
      if (is_array($filter)) {
        if ($filter[0] == 'search_api_language' && $filter[2] == '=') {
          $lang = $filter[1];
          if (isset($languages[$lang])) {
            unset($langs_to_add[$lang]);
          }
          else {
            throw new SearchApiException(t('Impossible combination of filters and languages. There is a filter for "@language", but allowed languages are: "@languages".', array('@language' => $lang, '@languages' => implode('", "', $languages))));
          }
        }
      }
      else {
        if ($filter->getConjunction() == 'AND') {
          $filters += $filter->getFilters();
        }
      }
    }
    if ($langs_to_add) {
      if (count($langs_to_add) == 1) {
        $this->condition('search_api_language', reset($langs_to_add));
      }
      else {
        $filter = $this->createFilter('OR');
        foreach ($langs_to_add as $lang) {
          $filter->condition('search_api_language', $lang);
        }
        $this->filter($filter);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute() {
    // Make sure to only execute this once per query.
    if (!$this->pre_execute) {
      $this->pre_execute = TRUE;
      // Add filter for languages.
      if (isset($this->options['languages'])) {
        $this->addLanguages($this->options['languages']);
      }

      // Add fulltext fields, unless set
      if ($this->fields === NULL) {
        $this->fields = $this->index->getFulltextFields();
      }

      // Preprocess query.
      $this->index->preprocessSearchQuery($this);

      // Let modules alter the query.
      drupal_alter('search_api_query', $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postExecute(array &$results) {
    // Postprocess results.
    $this->index->postprocessSearchResults($results, $this);
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
    $this->index_id = $this->index->machine_name;
    $keys = get_object_vars($this);
    unset($keys['index']);
    return array_keys($keys);
  }

  /**
   * Implements the magic __wakeup() method to reload the query's index.
   */
  public function __wakeup() {
    if (!isset($this->index) && !empty($this->index_id)) {
      $this->index = search_api_index_load($this->index_id);
      unset($this->index_id);
    }
  }

}
