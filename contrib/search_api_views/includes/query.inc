<?php
// $Id$

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
   * Array of all encountered errors. Each of these is fatal, meaning that a
   * non-empty $errors property will result in an empty result being returned.
   *
   * @var array
   */
  protected $errors;

  /**
   * Create the basic query object and fill with default values.
   */
  public function init($base_table, $base_field, $options) {
    try {
      $this->errors = array();
      parent::init($base_table, $base_field, $options);
      $id = substr($base_table, 17);
      $this->index = search_api_index_load($id);
      $this->query = $this->index->query(array('parse mode' => 'terms'));
      $this->options = $options;
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  public function execute(view $view) {
    if ($this->errors) {
      $view->result = array();
      $view->total_rows = $this->pager->total_rows = 0;
      $view->execute_time = 0;
      return;
    }

    try {
      $this->query->range($this->offset, $this->limit);
      $results = $this->query->execute();
      $view->total_rows = $this->pager->total_rows = $results['result count'];
      $this->pager->update_page_info();
      $view->result = array();
      if (!empty($results['results'])) {
        $this->addResults($results['results'], $view);
      }
      $view->execute_time = $results['performance']['complete'];
    }
    catch (Exception $e) {
      $this->errors[] = $e->getMessage();
      // Recursion to get the same error behaviour as above.
      $this->execute($view);
    }
  }

  /**
   * Helper function for adding results to a view in the format expected by the
   * view.
   */
  protected function addResults(array $results, view $view) {
    $entities = entity_load($this->index->entity_type, array_keys($results));
    foreach ($entities as $id => $entity) {
      // @todo Instead, extract required fields
      $entity->search_api_relevance = $results[$id]['score'];
      $view->result[$id] = $entity;
    }
  }

  //
  // Query interface methods (proxy to $this->query)
  //

  public function createFilter($conjunction = 'AND') {
    if (!$this->errors) {
      $this->query->createFilter($conjunction = 'AND');
    }
    return $this;
  }

  public function keys($keys = NULL) {
    if (!$this->errors) {
      $this->query->keys($keys = NULL);
    }
    return $this;
  }

  public function fields(array $fields) {
    if (!$this->errors) {
      $this->query->fields($fields);
    }
    return $this;
  }

  public function filter(SearchApiQueryFilter $filter) {
    if (!$this->errors) {
      $this->query->filter($filter);
    }
    return $this;
  }

  public function condition($field, $value, $operator = '=') {
    if (!$this->errors) {
      $this->query->condition($field, $value, $operator = '=');
    }
    return $this;
  }

  public function sort($field, $order = 'ASC') {
    if (!$this->errors) {
      $this->query->sort($field, $order = 'ASC');
    }
    return $this;
  }

  public function range($offset = NULL, $limit = NULL) {
    if (!$this->errors) {
      $this->query->range($offset = NULL, $limit = NULL);
    }
    return $this;
  }

  public function getIndex() {
    if (!$this->errors) {
      return $this->query->getIndex();
    }
  }

  public function &getKeys() {
    if (!$this->errors) {
      return $this->query->getKeys();
    }
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
  }

  public function &getOptions() {
    if (!$this->errors) {
      return $this->query->getOptions();
    }
  }

}
