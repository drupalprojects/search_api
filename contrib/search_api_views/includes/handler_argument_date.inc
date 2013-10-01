<?php


/**
 * Defines a contextual filter searching for a date or date range.
 */
class SearchApiViewsHandlerArgumentDate extends SearchApiViewsHandlerArgument {

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    if (empty($this->value)) {
      $this->fillValue();
    }

    if (empty($this->options['not'])) {
      $operator = '=';
      $conjunction = 'OR';
    }
    else {
      $operator = '<>';
      $conjunction = 'AND';
    }

    if (!empty($this->argument)) {
      $dates = preg_split('/[, ]/', $this->argument);

      if (!empty($dates)) {
        $filter = $this->query->createFilter($conjunction);
        foreach ($dates as $date) {
          $values = explode(';', $date);

          $is_range = (count($values) > 1);
          $my_filter = ($is_range ? $this->query->createFilter(empty($this->options['not']) ? 'AND' : 'OR') : $filter);
          $range_op = (empty($this->options['not']) ? '>=' : '<');
          $my_filter->condition($this->real_field, $this->getTimestamp($values[0]), $is_range ? $range_op : $operator);
          if ($is_range) {
            $range_op = (empty($this->options['not']) ? '<=' : '>');
            $my_filter->condition($this->real_field, strtotime('+1 day', $this->getTimestamp($values[1]))-1, $range_op);
            $filter->filter($my_filter);
          }
        }

        $this->query->filter($filter);
      }
    }
  }

  protected function getTimestamp($value) {
    if (is_numeric($value)) {
      return $value;
    }

    $date = new DateTime($value, date_default_timezone_object());
    $date->setTime(0, 0, 0);
    return $date->format('U');
  }

  /**
   * Get the title this argument will assign the view, given the argument.
   */
  public function title() {
    if (!empty($this->argument)) {
      if (empty($this->value)) {
        $this->fillValue();
      }
      $terms = array();
      foreach ($this->value as $tid) {
        $taxonomy_term = taxonomy_term_load($tid);
        if ($taxonomy_term) {
          $terms[] = check_plain($taxonomy_term->name);
        }
      }

      return $terms ? implode(', ', $terms) : check_plain($this->argument);
    }
    else {
      return check_plain($this->argument);
    }
  }

  /**
   * Fill $this->value with data from the argument.
   *
   * Uses views_break_phrase(), if appropriate.
   */
  protected function fillValue() {
    if (!empty($this->options['break_phrase'])) {
      views_break_phrase($this->argument, $this);
    }
    else {
      $this->value = array($this->argument);
    }
  }

}
