<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\filter\SearchApiFilterBoolean.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Defines a filter for filtering on boolean values.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_boolean")
 */
class SearchApiFilterBoolean extends BooleanOperator {

  use SearchApiFilterTrait;

}
