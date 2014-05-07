<?php

/**
 * @file
 * Contains SearchApiViewsHandlerArgumentString.
 */

namespace Drupal\search_api\Plugin\views\argument;

/**
 * Views argument handler class for handling string fields.
 *
 * @ViewsArgument("search_api_string")
 */
class SearchApiString extends SearchApiArgument {

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    if (empty($this->value)) {
      if (!empty($this->options['break_phrase'])) {
        $this->breakPhraseString($this->argument, $this);
      }
      else {
        $this->value = array($this->argument);
      }
    }

    parent::query($group_by);
  }

}
