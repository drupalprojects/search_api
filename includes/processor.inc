<?php
// $Id$

/**
 * Interface representing a Search API pre- and/or post-processor.
 */
interface SearchApiProcessorInterface {

  /**
   * Preprocess data items for indexing.
   *
   * @param $index
   *   The index on which the items will be indexed.
   * @param $items
   *   An array of entities to be indexed.
   */
  function preprocessIndexItems($index, &$items);

  /**
   * Preprocess a search query.
   *
   * @param $index
   *   The index on which the query will be executed.
   * @param $query
   *   The SearchApiQuery object representing the query.
   */
  function preprocessSearchQuery($index, $query);

  /**
   * Postprocess search results before display.
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls.
   *
   * @param $index
   *  The index on which the search was executed.
   * @param $results
   *   An array of entities that matched the search query.
   */
  function postprocessSearchResults($index, &$results);

}
