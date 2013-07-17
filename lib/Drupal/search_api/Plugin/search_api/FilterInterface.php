<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\FilterInterface.
 */

namespace Drupal\search_api\Plugin\search_api;

/**
 * Represents a filter on a search query.
 *
 * Filters apply conditions on one or more fields with a specific conjunction
 * (AND or OR) and may contain nested filters.
 */
interface FilterInterface {

  /**
   * Constructs a new filter that uses the specified conjunction.
   *
   * @param string $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   */
  public function __construct($conjunction = 'AND');

  /**
   * Sets this filter's conjunction.
   *
   * @param string $conjunction
   *   The conjunction to use for this filter - either 'AND' or 'OR'.
   *
   * @return FilterInterface
   *   The called object.
   */
  public function setConjunction($conjunction);

  /**
   * Adds a subfilter.
   *
   * @param FilterInterface $filter
   *   A FilterInterface object that should be added as a
   *   subfilter.
   *
   * @return FilterInterface
   *   The called object.
   */
  public function filter(FilterInterface $filter);

  /**
   * Adds a new ($field $operator $value) condition.
   *
   * @param string $field
   *   The field to filter on, e.g. 'title'.
   * @param mixed $value
   *   The value the field should have (or be related to by the operator).
   * @param string $operator
   *   The operator to use for checking the constraint. The following operators
   *   are supported for primitive types: "=", "<>", "<", "<=", ">=", ">". They
   *   have the same semantics as the corresponding SQL operators.
   *   If $field is a fulltext field, $operator can only be "=" or "<>", which
   *   are in this case interpreted as "contains" or "doesn't contain",
   *   respectively.
   *   If $value is NULL, $operator also can only be "=" or "<>", meaning the
   *   field must have no or some value, respectively.
   *
   * @return FilterInterface
   *   The called object.
   */
  public function condition($field, $value, $operator = '=');

  /**
   * Retrieves the conjunction used by this filter.
   *
   * @return string
   *   The conjunction used by this filter - either 'AND' or 'OR'.
   */
  public function getConjunction();

  /**
   * Return all conditions and nested filters contained in this filter.
   *
   * @return array
   *   An array containing this filter's subfilters. Each of these is either an
   *   array (field, value, operator), or another SearchApiFilter object.
   */
  public function &getFilters();

}
