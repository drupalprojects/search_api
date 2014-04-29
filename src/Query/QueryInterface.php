<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\QueryInterface.
 */

namespace Drupal\search_api\Query;

use Drupal\search_api\Index\IndexInterface;

/**
 * Represents a search query on a Search API index.
 *
 * Methods not returning something else will return the object itself, so calls
 * can be chained.
 */
interface QueryInterface {

  /**
   * Instantiates a new instance of this query class.
   *
   * @param IndexInterface $index
   *   The index for which the query should be created.
   * @param array $options
   *   (optional) The options to set for the query.
   *
   * @return static
   *   A query object to use.
   */
  public static function create(IndexInterface $index, array $options = array());

  /**
   * Retrieves the parse modes supported by this query class.
   *
   * @return array
   *   An associative array of parse modes recognized by objects of this class.
   *   The keys are the parse modes' ids, values are associative arrays
   *   containing the following entries:
   *   - name: The translated name of the parse mode.
   *   - description: (optional) A translated text describing the parse mode.
   */
  public function parseModes();

  /**
   * Creates a new filter to use with this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the filter - either 'AND' or 'OR'.
   *
   * @return \Drupal\search_api\Query\FilterInterface
   *   A filter object that is set to use the specified conjunction.
   */
  public function createFilter($conjunction = 'AND');

  /**
   * Sets the keys to search for.
   *
   * If this method is not called on the query before execution, this will be a
   * filter-only query.
   *
   * @param string|array|null $keys
   *   A string with the search keys, in one of the formats specified by
   *   getKeys(). A passed string will be parsed according to the set parse
   *   mode. Use NULL to not use any search keys.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   */
  public function keys($keys = NULL);

  /**
   * Sets the fields that will be searched for the search keys.
   *
   * If this is not called, all fulltext fields will be searched.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If one of the fields isn't of type "text".
   */
  // @todo Allow calling with NULL.
  public function fields(array $fields);

  /**
   * Adds a subfilter to this query's filter.
   *
   * @param \Drupal\search_api\Query\FilterInterface $filter
   *   A DefaultFilter object that should be added as a subfilter.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   */
  public function filter(FilterInterface $filter);

  /**
   * Adds a new ($field $operator $value) condition filter.
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
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   */
  public function condition($field, $value, $operator = '=');

  /**
   * Adds a sort directive to this search query.
   *
   * If no sort is manually set, the results will be sorted descending by
   * relevance.
   *
   * @param string $field
   *   The field to sort by. The special fields 'search_api_relevance' (sort by
   *   relevance) and 'search_api_id' (sort by item id) may be used.
   * @param string $order
   *   The order to sort items in - either 'ASC' or 'DESC'.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field is multi-valued or of a fulltext type.
   */
  public function sort($field, $order = 'ASC');

  /**
   * Adds a range of results to return.
   *
   * This will be saved in the query's options. If called without parameters,
   * this will remove all range restrictions previously set.
   *
   * @param int|null $offset
   *   The zero-based offset of the first result returned.
   * @param int|null $limit
   *   The number of results to return.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The called object.
   */
  public function range($offset = NULL, $limit = NULL);

  /**
   * Executes this search query.
   *
   * @return array
   *   An associative array containing the search results. The following keys
   *   are standardized:
   *   - 'result count': The overall number of results for this query, without
   *     range restrictions. Might be approximated, for large numbers.
   *   - results: An array of results, ordered as specified. The array keys are
   *     the items' IDs, values are arrays containing the following keys:
   *     - id: The item's internal ID.
   *     - datasource: The datasource to which this item belongs.
   *     - score: A float measuring how well the item fits the search.
   *     - fields: (optional) If set, an array containing some field values
   *       already ready-to-use. This allows search engines (or postprocessors)
   *       to store extracted fields so other modules don't have to extract them
   *       again. This fields should always be checked by modules that want to
   *       use field contents of the result items.
   *     - entity: (optional) If set, the fully loaded result item. This field
   *       should always be used by modules using search results, to avoid
   *       duplicate item loads.
   *     - excerpt: (optional) If set, an HTML text containing highlighted
   *       portions of the fulltext that match the query.
   *   - warnings: A numeric array of translated warning messages that may be
   *     displayed to the user.
   *   - ignored: A numeric array of search keys that were ignored for this
   *     search (e.g., because of being too short or stop words).
   *   Additional metadata may be returned in other keys. Only 'result count'
   *   and 'result' always have to be set, all other entries are optional.
   */
  public function execute();

  /**
   * Prepares the query object for the search.
   *
   * This method should always be called by execute() and contain all necessary
   * operations before the query is passed to the server's search() method.
   */
  public function preExecute();

  /**
   * Postprocesses the search results before they are returned.
   *
   * This method should always be called by execute() and contain all necessary
   * operations after the results are returned from the server.
   *
   * @param array $results
   *   The results returned by the server, which may be altered. The data
   *   structure is the same as returned by execute().
   */
  public function postExecute(array &$results);

  /**
   * Retrieves the index associated with this search.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The search index this query should be executed on.
   */
  public function getIndex();

  /**
   * Retrieves the search keys for this query.
   *
   * @return array|string|null
   *   This object's search keys - either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   *   Additionally, a '#negation' key might be present, which means – unless it
   *   maps to a FALSE value – that the search keys contained in that array
   *   should be negated, i.e. not be present in returned results. The negation
   *   works on the whole array, not on each contained term individually – i.e.,
   *   with the "AND" conjunction and negation, only results that contain all
   *   the terms in the array should be excluded; with the "OR" conjunction and
   *   negation, all results containing one or more of the terms in the array
   *   should be excluded.
   *
   * @see keys()
   */
  public function &getKeys();

  /**
   * Retrieves the unparsed search keys for this query as originally entered.
   *
   * @return array|string|null
   *   The unprocessed search keys, exactly as passed to this object. Has the
   *   same format as the return value of getKeys().
   *
   * @see keys()
   */
  public function getOriginalKeys();

  /**
   * Retrieves the fulltext fields that will be searched for the search keys.
   *
   * @return array
   *   An array containing the fields that should be searched for the search
   *   keys.
   *
   * @see fields()
   */
  public function &getFields();

  /**
   * Retrieves the filter object associated with this search query.
   *
   * @return \Drupal\search_api\Query\FilterInterface
   *   This object's associated filter object.
   */
  public function getFilter();

  /**
   * Retrieves the sorts set for this query.
   *
   * @return array
   *   An array specifying the sort order for this query. Array keys are the
   *   field names in order of importance, the values are the respective order
   *   in which to sort the results according to the field.
   *
   * @see sort()
   */
  public function &getSort();

  /**
   * Retrieves an option set on this search query.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $default
   *   The value to return if the specified option is not set.
   *
   * @return mixed
   *   The value of the option with the specified name, if set. NULL otherwise.
   */
  public function getOption($name, $default = NULL);

  /**
   * Sets an option for this search query.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $value
   *   The new value of the option.
   *
   * @return mixed
   *   The option's previous value, or NULL if none was set.
   */
  public function setOption($name, $value);

  /**
   * Retrieves all options set for this search query.
   *
   * The return value is a reference to the options so they can also be altered
   * this way.
   *
   * @return array
   *   An associative array of query options.
   */
  public function &getOptions();

}
