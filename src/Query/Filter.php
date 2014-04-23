<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\Filter.
 */

namespace Drupal\search_api\Query;

/**
 * Provides a standard implementation of FilterInterface.
 */
class Filter implements FilterInterface {

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
   * An array of tags set on this filter.
   *
   * @var array
   */
  protected $tags;

  /**
   * {@inheritdoc}
   */
  public function __construct($conjunction = 'AND', array $tags = array()) {
    $this->setConjunction($conjunction);
    $this->filters = array();
    $this->tags = array_combine($tags, $tags);
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

  /**
   * {@inheritdoc}
   */
  public function hasTag($tag) {
    return isset($this->tags[$tag]);
  }

  /**
   * {@inheritdoc}
   */
  public function &getTags() {
    return $this->tags;
  }

  /**
   * Implements the magic __clone() method to clone nested filters, too.
   */
  public function __clone() {
    foreach ($this->filters as $i => $filter) {
      if (is_object($filter)) {
        $this->filters[$i] = clone $filter;
      }
    }
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    // Special case for a single, nested filter:
    if (count($this->filters) == 1 && is_object($this->filters[0])) {
      return (string) $this->filters[0];
    }
    $ret = array();
    foreach ($this->filters as $filter) {
      if (is_object($filter)) {
        $ret[] = "[\n  " . str_replace("\n", "\n    ", (string) $filter) . "\n  ]";
      }
      else {
        $ret[] = "$filter[0] $filter[2] " . str_replace("\n", "\n    ", var_export($filter[1], TRUE));
      }
    }
    return $ret ? '  ' . implode("\n{$this->conjunction}\n  ", $ret) : '';
  }

}
