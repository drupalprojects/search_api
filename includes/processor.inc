<?php
// $Id$

/**
 * Interface representing a Search API pre- and/or post-processor.
 *
 * Enabling classes for preprocessing and postprocessing is done seperately.
 * However, an enabled index data preprocessor will also be used to preprocess
 * search queries on that index, since most of the time the same steps should be
 * executed in both phases. If this does not apply to a specific processor, the
 * unnecessary method can be left blank.
 */
interface SearchApiProcessorInterface {

  /**
   * Construct a processor.
   *
   * @param array $options
   *   The processor options set for this index.
   */
  public function __construct(array $options = array());

  /**
   * Display a form for configuring this processor.
   * Since forcing users to specify options for disabled processors makes no
   * sense, none of the form elements should have the '#required' attribute set.
   *
   * @return
   *   A form array for configuring this processor, or FALSE if no configuration
   *   is possible.
   */
  public function configurationForm();

  /**
   * Preprocess data items for indexing. Data added by data alter callbacks will
   * be available on the items.
   *
   * Typically, a preprocessor will execute its preprocessing (e.g. stemming,
   * n-grams, word splitting, stripping stop words, etc.) only on the items'
   * search_api_fulltext fields, if set. Other fields should usually be left
   * untouched.
   *
   * @param $index
   *   The index on which the items will be indexed.
   * @param $items
   *   An array of entities to be indexed.
   */
  public function preprocessIndexItems($index, &$items);

  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param $index
   *   The index on which the query will be executed.
   * @param $query
   *   The SearchApiQuery object representing the query.
   */
  public function preprocessSearchQuery($index, $query);

  /**
   * Postprocess search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param $index
   *  The index on which the search was executed.
   * @param $query
   *   The SearchApiQuery object representing the executed query.
   * @param $results
   *   An array containing the search results. See
   *   SearchApiServiceInterface->search() for the detailled format.
   */
  public function postprocessSearchResults($index, $query, &$results);

}
