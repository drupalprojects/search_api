<?php

/**
 * @file
 * Contains the SearchApiViewsHandlerFilterOptions class.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;

/**
 * Views filter handler for fields with a limited set of possible values.
 *
 * @ViewsFilter("search_api_options")
 */
class SearchApiFilterOptions extends SearchApiFilter {

  /**
   * Stores the values which are available on the form.
   *
   * @var array
   */
  protected $value_options = NULL;

  /**
   * The type of form element used to display the options.
   *
   * @var string
   */
  protected $value_form_type = 'checkboxes';

  /**
   * Retrieves a wrapper for this filter's field.
   *
   * @return EntityMetadataWrapper|null
   *   A wrapper for the field which this filter uses.
   */
  protected function get_wrapper() {
    if ($this->query) {
      $index = $this->query->getIndex();
    }
    elseif (substr($this->view->base_table, 0, 17) == 'search_api_index_') {
      $index = entity_load('search_api_index', substr($this->view->base_table, 17));
    }
    else {
      return NULL;
    }
    $wrapper = $index->entityWrapper(NULL, TRUE);
    $parts = explode(':', $this->real_field);
    foreach ($parts as $i => $part) {
      if (!isset($wrapper->$part)) {
        return NULL;
      }
      $wrapper = $wrapper->$part;
      $info = $wrapper->info();
      if ($i < count($parts) - 1) {
        // Unwrap lists.
        $level = search_api_list_nesting_level($info['type']);
        for ($j = 0; $j < $level; ++$j) {
          $wrapper = $wrapper[0];
        }
      }
    }

    return $wrapper;
  }

  /**
   * Fills the value_options property with all possible options.
   */
  protected function getValueOptions() {
    if (isset($this->value_options)) {
      return;
    }

    $wrapper = $this->get_wrapper();
    if ($wrapper) {
      $this->value_options = $wrapper->optionsList('view');
    }
    else {
      $this->value_options = array();
    }
  }

  /**
   * Provide a list of options for the operator form.
   */
  public function operatorOptions() {
    $options = array(
      '=' => $this->t('Is one of'),
      'all of' => $this->t('Is all of'),
      '<>' => $this->t('Is none of'),
      'empty' => $this->t('Is empty'),
      'not empty' => $this->t('Is not empty'),
    );
    // "Is all of" doesn't make sense for single-valued fields.
    if (empty($this->definition['multi-valued'])) {
      unset($options['all of']);
    }
    return $options;
  }

  /**
   * Set "reduce" option to FALSE by default.
   */
  public function defaultExposedOptions() {
    parent::defaultExposeOptions();
    $this->options['expose']['reduce'] = FALSE;
  }

  /**
   * Add the "reduce" option to the exposed form.
   */
  public function buildExposedForm(&$form, &$form_state) {
    parent::buildExposedForm($form, $form_state);
    $form['expose']['reduce'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Limit list to selected items'),
      '#description' => $this->t('If checked, the only items presented to the user will be the ones selected here.'),
      '#default_value' => !empty($this->options['expose']['reduce']),
    );
  }

  /**
   * Define "reduce" option.
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['expose']['contains']['reduce'] = array('default' => FALSE);
    return $options;
  }

  /**
   * Reduce the options according to the selection.
   */
  protected function reduceValueOptions() {
    foreach ($this->value_options as $id => $option) {
      if (!isset($this->options['value'][$id])) {
        unset($this->value_options[$id]);
      }
    }
    return $this->value_options;
  }

  /**
   * Save set checkboxes.
   */
  public function valueSubmit($form, &$form_state) {
    // Drupal's FAPI system automatically puts '0' in for any checkbox that
    // was not set, and the key to the checkbox if it is set.
    // Unfortunately, this means that if the key to that checkbox is 0,
    // we are unable to tell if that checkbox was set or not.

    // Luckily, the '#value' on the checkboxes form actually contains
    // *only* a list of checkboxes that were set, and we can use that
    // instead.

    $form_state['values']['options']['value'] = $form['value']['#value'];
  }

  /**
   * Provide a form for setting options.
   */
  public function valueForm(&$form, &$form_state) {
    $this->getValueOptions();
    if (!empty($this->options['expose']['reduce']) && !empty($form_state['exposed'])) {
      $options = $this->reduceValueOptions();
    }
    else {
      $options = $this->value_options;
    }

    $form['value'] = array(
      '#type' => $this->value_form_type,
      '#title' => empty($form_state['exposed']) ? $this->t('Value') : '',
      '#options' => $options,
      '#multiple' => TRUE,
      '#size' => min(4, count($options)),
      '#default_value' => is_array($this->value) ? $this->value : array(),
    );

    // Hide the value box if the operator is 'empty' or 'not empty'.
    // Radios share the same selector so we have to add some dummy selector.
    if (empty($form_state['exposed'])) {
      $form['value']['#states']['visible'] = array(
        ':input[name="options[operator]"],dummy-empty' => array('!value' => 'empty'),
        ':input[name="options[operator]"],dummy-not-empty' => array('!value' => 'not empty'),
      );
    }
    elseif (!empty($this->options['expose']['use_operator'])) {
      $name = $this->options['expose']['operator_id'];
      $form['value']['#states']['visible'] = array(
        ':input[name="' . $name . '"],dummy-empty' => array('!value' => 'empty'),
        ':input[name="' . $name . '"],dummy-not-empty' => array('!value' => 'not empty'),
      );
    }
  }

  /**
   * Provides a summary of this filter's value for the admin UI.
   */
  public function adminSummary() {
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    if ($this->operator === 'empty') {
      return $this->t('is empty');
    }
    if ($this->operator === 'not empty') {
      return $this->t('is not empty');
    }

    if (!is_array($this->value)) {
      return;
    }

    $operator_options = $this->operatorOptions();
    $operator = $operator_options[$this->operator];
    $values = '';

    // Remove every element which is not known.
    $this->getValueOptions();
    foreach ($this->value as $i => $value) {
      if (!isset($this->value_options[$value])) {
        unset($this->value[$i]);
      }
    }
    // Choose different kind of ouput for 0, a single and multiple values.
    if (count($this->value) == 0) {
      return $this->operator != '<>' ? $this->t('none') : $this->t('any');
    }
    elseif (count($this->value) == 1) {
      switch ($this->operator) {
        case '=':
        case 'all of':
          $operator = '=';
          break;

        case '<>':
          $operator = '<>';
          break;
      }
      // If there is only a single value, use just the plain operator, = or <>.
      $operator = String::checkPlain($operator);
      $values = String::checkPlain($this->value_options[reset($this->value)]);
    }
    else {
      foreach ($this->value as $value) {
        if ($values !== '') {
          $values .= ', ';
        }
        if (Unicode::strlen($values) > 20) {
          $values .= '…';
          break;
        }
        $values .= String::checkPlain($this->value_options[$value]);
      }
    }

    return $operator . (($values !== '') ? ' ' . $values : '');
  }

  /**
   * Add this filter to the query.
   */
  public function query() {
    if ($this->operator === 'empty') {
      $this->query->condition($this->realField, NULL, '=', $this->options['group']);
      return;
    }
    if ($this->operator === 'not empty') {
      $this->query->condition($this->realField, NULL, '<>', $this->options['group']);
      return;
    }

    // Extract the value.
    while (is_array($this->value) && count($this->value) == 1) {
      $this->value = reset($this->value);
    }

    // Determine operator and conjunction. The defaults are already right for
    // "all of".
    $operator = '=';
    $conjunction = 'AND';
    switch ($this->operator) {
      case '=':
        $conjunction = 'OR';
        break;

      case '<>':
        $operator = '<>';
        break;
    }

    // If the value is an empty array, we either want no filter at all (for
    // "is none of"), or want to find only items with no value for the field.
    if ($this->value === array()) {
      if ($operator != '<>') {
        $this->query->condition($this->realField, NULL, '=', $this->options['group']);
      }
      return;
    }

    if (is_scalar($this->value) && $this->value !== '') {
      $this->query->condition($this->realField, $this->value, $operator, $this->options['group']);
    }
    elseif ($this->value) {
      $filter = $this->query->createFilter($conjunction);
      // $filter will be NULL if there were errors in the query.
      if ($filter) {
        foreach ($this->value as $v) {
          $filter->condition($this->realField, $v, $operator);
        }
        $this->query->filter($filter, $this->options['group']);
      }
    }
  }

}
