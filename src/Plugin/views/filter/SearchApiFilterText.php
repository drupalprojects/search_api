<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilterText.
 */

namespace Drupal\search_api\Plugin\views\filter;

/**
 * Views filter handler class for handling fulltext fields.
 *
 * @ViewsFilter("search_api_text")
 */
class SearchApiFilterText extends SearchApiFilter {

  /**
   * Provide a list of options for the operator form.
   */
  public function operatorOptions() {
    return array('=' => t('contains'), '<>' => t("doesn't contain"));
  }

}
