<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\QueryResultInterface.
 */

namespace Drupal\search_api\Query;

/**
 * Represents the result set of a search query.
 */
interface ResultSetInterface extends \Iterator, \Countable {

  /**
   * Create instance.
   *
   * @param array $result_items
   *   The query result items.
   * @param array $warnings
   *   A numeric array of translated warning messages.
   * @param array $ignored_search_keys
   *   A numeric array of search keys that were ignored.
   */
  public function __construct(array $result_items, array $warnings = array(), array $ignored_search_keys = array());

  /**
   * Set the query result items.
   *
   * @param array $result_items
   *   The query result items.
   */
  public function setResultItems(array $result_items);

  /**
   * Returns an array of warnings if any.
   *
   * @return array
   *   A numeric array of translated warning messages that may be displayed to
   *   the user.
   */
  public function getWarnings();

  /**
   * Returns an array of ignored search keys if any.
   *
   * @return array
   *   A numeric array of search keys that were ignored for this search
   *   (e.g., because of being too short or stop words).
   */
  public function getIgnoredSearchKeys();
}
