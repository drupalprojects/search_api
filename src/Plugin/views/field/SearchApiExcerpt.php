<?php
/**
 * @file Excerpt.php
 */

namespace Drupal\search_api\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that contains a search result
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("search_api_excerpt")
 */
class SearchApiExcerpt extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return $value;
  }

}