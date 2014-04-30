<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\QueryResult.
 */

namespace Drupal\search_api\Query;

use Drupal\search_api\Item\Item;

/**
 * Represents the result set of a search query.
 */
class ResultSet implements ResultSetInterface {

  /**
   * The result items.
   * @var \Drupal\search_api\Item\Item[]
   */
  protected $resultItems = array();

  /**
   *  The position within the result items.
   * @var int
   */
  protected $position = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $result_items) {
    $this->setResultItems($result_items);
  }

  /**
   * {@inheritdoc}
   */
  public function setResultItems(array $result_items) {
    $this->resultItems = $result_items;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->resultItems);
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    $this->position++;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\search_api\Item\Item
   *   The current result item.
   */
  public function current() {
    return $this->resultItems[$this->position];
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return isset($this->resultItems[$this->position]);
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->position = 0;
  }
}
