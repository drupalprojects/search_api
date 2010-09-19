<?php
// $Id$

/**
 * Views filter handler class for handling fulltext fields.
 */
class SearchApiViewsHandlerFilterFulltext extends SearchApiViewsHandlerFilterText {

  // @todo Add option for selecting the fields to search.
  // If different fields are selected for different filters, the query() method
  // would get messy, though.

  /**
   * Add this filter to the query.
   */
  function query() {
    $old = $this->query->getKeys();
    $this->query->keys($this->value);
    if ($this->operator != '=') {
      $keys = &$this->query->getKeys();
      if (is_array($keys)) {
        $keys['#negation'] = TRUE;
      }
    }
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
