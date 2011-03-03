<?php

/**
 * Views query class using a Search API index as the data source.
 */
class SearchApiViewsQuery extends views_plugin_query {

  /**
   * Number of results to display.
   *
   * @var int
   */
  protected $limit;

  /**
   * Offset of first displayed result.
   *
   * @var int
   */
  protected $offset;

  /**
   * The index this view accesses.
   *
   * @var SearchApiIndex
   */
  protected $index;

  /**
   * The query that will be executed.
   *
   * @var SearchApiQueryInterface
   */
  protected $query;

  /**
   * The results returned by the query, after it was executed.
   *
   * @var array
   */
  protected $search_api_results = array();

  /**
   * Array of all encountered errors. Each of these is fatal, meaning that a
   * non-empty $errors property will result in an empty result being returned.
   *
   * @var array
   */
  protected $errors;

  /**
   * The names of all fields whose value is required by a handler. The format
   * follows the same as Search API field identifiers (parent:child).
   *
   * @var array
   */
  protected $fields;

  /**
   * Create the basic query object and fill with default values.
   */
  public function init($base_table, $base_field, array $options) {
    try {
      $this->errors = array();
      parent::init($base_table, $base_field, $options);
      $this->fields = array();
      if (substr($base_table, 0, 17) == 'search_api_index_') {
        $id = substr($base_table, 17);
        $this->index = search_api_index_load($id);
        $this->query = $this->index->query(array(
          'parse mode' => 'terms',
        ));
      }
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Add a field that should be retrieved from the results by this view.
   *
   * @param $field
   *   The field's identifier, as used by the Search API. E.g., "title" for a
   *   node's title, "author:name" for a node's author's name.
   *
   * @return SearchApiViewsQuery
   *   The called object.
   */
  public function addField($field) {
    $this->fields[$field] = TRUE;
  }

  /**
   * Builds the necessary info to execute the query.
   */
  public function build($view) {
    $view->init_pager();

    // Let the pager modify the query to add limits.
    $this->pager->query();
  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  public function execute($view) {
    if ($this->errors) {
      foreach ($this->errors as $msg) {
        drupal_set_message($msg, 'error');
      }
      $view->result = array();
      $view->total_rows = 0;
      $view->execute_time = 0;
      return;
    }

    try {
      $start = microtime(TRUE);
      // Add range and search ID (if it wasn't already set).
      $this->query->range($this->offset, $this->limit);
      if ($this->query->getOption('search id') == get_class($this->query)) {
        $this->query->setOption('search id', 'search_api_views:' . $view->name);
      }

      // Execute the search.
      $results = $this->query->execute();
      $this->search_api_results = $results;

      // Store the results.
      $this->pager->total_items = $view->total_rows = $results['result count'];
      $this->pager->update_page_info();
      $view->result = array();
      if (!empty($results['results'])) {
        $this->addResults($results['results'], $view);
      }
      // We shouldn't use $results['performance']['complete'] here, since
      // extracting the results probably takes considerable time as well.
      $view->execute_time = microtime(TRUE) - $start;
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
      // Recursion to get the same error behaviour as above.
      return $this->execute($view);
    }
  }

  /**
   * Helper function for adding results to a view in the format expected by the
   * view.
   */
  protected function addResults(array $results, $view) {
    // Maybe the service class or a postprocessor already set the entities.
    foreach ($results as $id => $result) {
      if (!empty($result['entity']) && ($fields = $this->extractFields($result['entity']))) {
        $view->result[$id] = $fields;
        $view->result[$id]['search_api_relevance'] = $result['score'];
      }
      else {
        $ids[] = $id;
      }
    }
    if (empty($ids)) {
      return;
    }

    $entities = entity_load($this->index->entity_type, $ids);
    foreach ($entities as $id => $entity) {
      try {
        if (!is_object($entity)) {
          continue;
        }
        $wrapper = entity_metadata_wrapper($this->index->entity_type, $entity);
        $view->result[$id]['entity'] = $entity;
        $view->result[$id]['search_api_relevance'] = $results[$id]['score'];
        $view->result[$id] += $this->extractFields($wrapper, $this->fields);
      }
      catch (Exception $e) {
        continue;
      }
    }
  }

  /**
   * Helper function for extracting all necessary fields from an entity.
   */
  // @todo Optimize
  protected function extractFields(EntityMetadataWrapper $wrapper, array $all_fields) {
    $fields = array();
    foreach ($all_fields as $key => $true) {
      $fields[$key]['type'] = 'string';
    }
    $fields = search_api_extract_fields($wrapper, $fields, array('sanitized' => TRUE));
    $ret = array();
    foreach ($all_fields as $key => $true) {
      $ret[$key] = isset($fields[$key]['value']) ? $fields[$key]['value'] : '';
    }
    return $ret;
  }

  /**
   * API function for accessing the raw Search API query object.
   *
   * @return SearchApiQueryInterface
   *   An associative array containing the search results, as specified by
   *   SearchApiQueryInterface::execute().
   */
  public function getSearchApiQuery() {
    return $this->query;
  }

  /**
   * API function for accessing the raw Search API results.
   *
   * @return array
   *   An associative array containing the search results, as specified by
   *   SearchApiQueryInterface::execute().
   */
  public function getSearchApiResults() {
    return $this->search_api_results;
  }

  //
  // Query interface methods (proxy to $this->query)
  //

  public function createFilter($conjunction = 'AND') {
    if (!$this->errors) {
      return $this->query->createFilter($conjunction);
    }
  }

  public function keys($keys = NULL) {
    if (!$this->errors) {
      $this->query->keys($keys);
    }
    return $this;
  }

  public function fields(array $fields) {
    if (!$this->errors) {
      $this->query->fields($fields);
    }
    return $this;
  }

  public function filter(SearchApiQueryFilterInterface $filter) {
    if (!$this->errors) {
      $this->query->filter($filter);
    }
    return $this;
  }

  public function condition($field, $value, $operator = '=') {
    if (!$this->errors) {
      $this->query->condition($field, $value, $operator);
    }
    return $this;
  }

  public function sort($field, $order = 'ASC') {
    if (!$this->errors) {
      $this->query->sort($field, $order);
    }
    return $this;
  }

  public function range($offset = NULL, $limit = NULL) {
    if (!$this->errors) {
      $this->query->range($offset, $limit);
    }
    return $this;
  }

  public function getIndex() {
    return $this->index;
  }

  public function &getKeys() {
    if (!$this->errors) {
      return $this->query->getKeys();
    }
    $ret = NULL;
    return $ret;
  }

  public function getOriginalKeys() {
    if (!$this->errors) {
      return $this->query->getOriginalKeys();
    }
  }

  public function &getFields() {
    if (!$this->errors) {
      return $this->query->getFields();
    }
    $ret = NULL;
    return $ret;
  }

  public function getFilter() {
    if (!$this->errors) {
      return $this->query->getFilter();
    }
  }

  public function &getSort() {
    if (!$this->errors) {
      return $this->query->getSort();
    }
    $ret = NULL;
    return $ret;
  }

  public function &getOptions() {
    if (!$this->errors) {
      return $this->query->getOptions();
    }
    $ret = NULL;
    return $ret;
  }

}
