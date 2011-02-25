<?php

/**
 * Views filter handler class for handling fields with a limited set of possible
 * values.
 *
 * Definition items:
 * - options: An array of possible values for this field.
 */
class SearchApiViewsHandlerFilterOptions extends SearchApiViewsHandlerFilter {

  /**
   * Provide a list of options for the operator form.
   */
  public function operator_options() {
    return array(
      '=' => t('is one of'),
      '<>' => t('is not one of'),
    );
  }

  /**
   * Provide a form for setting options.
   */
  public function value_form(&$form, &$form_state) {
    $form['value'] = array(
      '#type' => 'select',
      '#title' => empty($form_state['exposed']) ? t('Value') : '',
      '#options' => $this->definition['options'],
      '#multiple' => TRUE,
      '#size' => min(4, count($this->definition['options'])),
      '#default_value' => isset($this->value) ? $this->value : array(),
    );
  }

  /**
   * Provides a summary of this filter's value for the admin UI.
   */
  function admin_summary() {
    if (!empty($this->options['exposed'])) {
      return t('exposed');
    }

    if (!is_array($this->value)) {
      return;
    }

    $operator_options = $this->operator_options();
    $operator = $operator_options[$this->operator];
    $values = '';

    // Remove every element which is not known.
    foreach ($this->value as $i => $value) {
      if (!isset($this->definition['options'][$value])) {
        unset($this->value[$i]);
      }
    }
    // Choose different kind of ouput for 0, a single and multiple values.
    if (count($this->value) == 0) {
      return $this->operator == '=' ? t('none') : t('any');
    }
    else if (count($this->value) == 1) {
      // If there is only a single value, use just the plain operator, = or <>.
      $operator = check_plain($this->operator);
      $values = check_plain($this->definition['options'][reset($this->value)]);
    }
    else {
      foreach ($this->value as $value) {
        if ($values !== '') {
          $values .= ', ';
        }
        if (drupal_strlen($values) > 20) {
          $values .= '…';
          break;
        }
        $values .= check_plain($this->definition['options'][$value]);
      }
    }

    return $operator . (($values !== '') ? ' ' . $values : '');
  }

  /**
   * Add this filter to the query.
   */
  public function query() {
    while (is_array($this->value) && count($this->value) == 1) {
      $this->value = array_shift($this->value);
    }
    if (is_scalar($this->value) && $this->value !== '') {
      $this->query->condition($this->real_field, $this->value, $this->operator);
    }
    elseif($this->value) {
      if ($this->operator == '=') {
        $filter = $this->query->createFilter('OR');
        // $filter will be NULL if there were errors in the query.
        if ($filter) {
          foreach ($this->value as $v) {
            $filter->condition($this->real_field, $v, '=');
          }
          $this->query->filter($filter);
        }
      }
      else {
        foreach ($this->value as $v) {
          $this->query->condition($this->real_field, $v, $this->operator);
        }
      }
    }
  }

}
