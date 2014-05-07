<?php

/**
 * @file
 *   Contains the SearchApiViewsHandlerFilterLanguage class.
 */

namespace Drupal\search_api\Plugin\views\filter;

/**
 * Views filter handler class for handling the special "Item language" field.
 *
 * Definition items:
 * - options: An array of possible values for this field.
 *
 * @ViewsFilter("search_api_language")
 */
class SearchApiLanguage extends SearchApiFilterOptions {

  /**
   * {@inheritdoc}
   */
  protected function getValueOptions() {
    parent::getValueOptions();
    $this->value_options = array(
      'current' => t("Current user's language"),
      'default' => t('Default site language'),
    ) + $this->value_options;
  }

  /**
   * Add this filter to the query.
   */
  public function query() {
    global $language_content;

    if (!is_array($this->value)) {
      $this->value = $this->value ? array($this->value) : array();
    }
    foreach ($this->value as $i => $v) {
      if ($v == 'current') {
        $this->value[$i] = $language_content->language;
      }
      elseif ($v == 'default') {
        $this->value[$i] = \Drupal::languageManager()->getDefaultLanguage('language');
      }
    }
    parent::query();
  }

}
