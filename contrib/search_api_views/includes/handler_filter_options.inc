<?php
// $Id$

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
    return array('=' => t('is one of'), '<>' => t('is not one of'));
  }

  /**
   * Provide a form for setting options.
   */
  public function value_form(&$form, &$form_state) {
    while (is_array($this->value)) {
      $this->value = $this->value ? array_shift($this->value) : NULL;
    }
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
   * Add this filter to the query.
   */
  public function query() {
    while (is_array($this->value) && count($this->value) == 1) {
      $this->value = array_shift($this->value);
    }
    if (is_scalar($this->value)) {
      $this->query->condition($this->real_field, $this->value, $this->operator);
    }
    elseif($this->value) {
      if ($this->operator == '=') {
        $filter = $this->query->createFilter('OR');
        foreach ($this->value as $v) {
          $filter->condition($this->real_field, $v, '=');
        }
        $this->query->filter($filter);
      }
      else {
        foreach ($this->value as $v) {
          $this->query->condition($this->real_field, $v, $this->operator);
        }
      }
    }
  }

}
