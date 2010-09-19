<?php
// $Id$

/**
 * Views argument handler class for handling fulltext fields.
 */
class SearchApiViewsHandlerArgumentFulltext extends SearchApiViewsHandlerArgumentText {

  // @todo Add option for selecting the fields to search.
  // If different fields are selected for different filters, the query() method
  // would get messy, though.

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query() {
    $old = $this->query->getKeys();
    $this->query->keys($this->value);
    if ($old) {
      $keys = &$this->query->getKeys();
      if (is_array($keys)) {
        $keys[] = $old;
      }
      else {
        $keys = print_r($old, TRUE) . ' ' . $keys;
      }
    }
  }

}
