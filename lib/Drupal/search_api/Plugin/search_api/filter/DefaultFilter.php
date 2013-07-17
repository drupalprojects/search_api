<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\filter\DefaultFilter.
 */

namespace Drupal\search_api\Plugin\search_api\filter;

/**
 * Provides a standard implementation of FilterInterface.
 */
class DefaultFilter implements FilterInterface {

  /**
   * Array containing subfilters.
   *
   * Each of these is either an array (field, value, operator), or another
   * SearchApiFilter object.
   *
   * @var array
   */
  protected $filters;

  /**
   * String specifying this filter's conjunction ('AND' or 'OR').
   *
   * @var string
   */
  protected $conjunction;

  /**
   * {@inheritdoc}
   */
  public function __construct($conjunction = 'AND') {
    $this->setConjunction($conjunction);
    $this->filters = array();
  }

  /**
   * {@inheritdoc}
   */
  public function setConjunction($conjunction) {
    $this->conjunction = strtoupper(trim($conjunction)) == 'OR' ? 'OR' : 'AND';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function filter(FilterInterface $filter) {
    $this->filters[] = $filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value, $operator = '=') {
    $this->filters[] = array($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * {@inheritdoc}
   */
  public function &getFilters() {
    return $this->filters;
  }

}
