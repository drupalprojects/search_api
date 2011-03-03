<?php

/**
 * Dummy interface for explaining all additions to the query class that the
 * "search_api_facets" feature implies.
 */
interface SearchApiFacetsQueryInterface extends SearchApiQueryInterface {

  /**
   * @param array $options
   *   This can now contain another key:
   *   - search_api_facets: An array of facets to return for this query, along
   *     with the results. The array is keyed by the unique identifier of the
   *     facet, the values are arrays with the following keys:
   *     - field: The field to construct facets for.
   *     - limit: The maximum number of facet terms to return. 0 or an empty
   *       value means no limit.
   *     - min_count: The minimum number of results a facet value has to have in
   *       order to be returned.
   *     - missing: If TRUE, a facet for all items with no value for this field
   *       should be returned (if it conforms to limit and min_count).
   */
  public function __construct(SearchApiIndex $index, array $options = array());

  /**
   * @return array
   *   If $options['search_api_facets'] is not empty, the returned array should
   *   have an additional entry:
   *   - search_api_facets: An array of possible facets for this query. The
   *     array should be keyed by the facets' unique identifiers, and contain
   *     a numeric array of facet terms, sorted descending by result count. A
   *     term is represented by an array with the following keys:
   *     - count: Number of results for this term.
   *     - filter: The filter to apply when selecting this facet term. A filter
   *       is a string of one of the following forms:
   *       - "VALUE": Filter by the literal value VALUE (not only for string
   *         types).
   *       - [VALUE1 VALUE2]: Filter for a value between VALUE1 and VALUE2. Use
   *         parantheses for excluding the border values and square brackets for
   *         including them. An asterisk (*) can be used as a wildcard, e.g.
   *         (* 0) or [* 0) would be a filter for all negative values.
   *       - !: Filter for items without a value for this field (i.e., the
   *         "missing" facet).
   */
  public function execute();

}
