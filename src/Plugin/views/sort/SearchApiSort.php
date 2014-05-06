<?php

/**
 * @file
 * Contains SearchApiViewsHandlerSort.
 */

namespace Drupal\search_api\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Class for sorting results according to a specified field.
 *
 * @ViewsSort("search_api_sort")
 */
class SearchApiSort extends SortPluginBase {

  /**
   * The associated views query object.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * Called to add the sort to a query.
   */
  public function query() {
    // When there are exposed sorts, the "exposed form" plugin will set
    // $query->orderby to an empty array. Therefore, if that property is set,
    // we here remove all previous sorts.
    if (isset($this->query->orderby)) {
      unset($this->query->orderby);
      $sort = &$this->query->getSort();
      $sort = array();
    }
    $this->query->sort($this->realField, $this->options['order']);
  }

}
