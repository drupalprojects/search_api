<?php

/**
 * Class for sorting results according to a specified field.
 */
class SearchApiViewsHandlerSort extends views_handler_sort {

  /**
   * The associated views query object.
   *
   * @var SearchApiViewsQuery
   */
  public $query;

  /**
   * Called to add the sort to a query.
   */
  public function query() {
    $this->query->sort($this->real_field, $this->options['order']);
  }

}
