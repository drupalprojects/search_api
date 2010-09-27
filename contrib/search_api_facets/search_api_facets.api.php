<?php
// $Id$

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
   *     - limit: The maximum number of facet terms to return.
   *     - min_count: The minimum number of results a facet value has to have in
   *       order to be returned.
   */
  public function __construct(SearchApiIndex $index, array $options = array());

  /**
   * @return array
   *   If $options['search_api_facets'] is not empty, the returned array should
   *   have an additional entry:
   *   - search_api_facets: An array of possible facets for this query. The
   *     array should be keyed by the facets' unique identifiers, and contain
   *     a numeric array of facet terms. A term is represented by an array with
   *     the following keys:
   *     - name: Some textual representation of the term, which might be
   *       displayed to the user.
   *     - count: Number of results for this term.
   *     - filter: An array of filters to apply when selecting this facet term.
   *       // @todo Describe filter.
   */
  public function execute();

}
