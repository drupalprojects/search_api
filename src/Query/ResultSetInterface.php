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
   */
  public function __construct(array $result_items);

  /**
   * Set the query result items.
   *
   * @param array $result_items
   *   The query result items.
   */
  public function setResultItems(array $result_items);
}
