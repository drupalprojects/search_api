<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilter.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Views filter handler base class for handling all "normal" cases.
 *
 * @ViewsFilter("search_api_filter")
 */
class SearchApiFilter extends FilterPluginBase {

  /**
   * The value to filter for.
   *
   * @var mixed
   */
  public $value;

  /**
   * The operator used for filtering.
   *
   * @var string
   */
  public $operator;

  /**
   * The associated views query object.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * Provide a list of options for the operator form.
   */
  public function operatorOptions() {
    return array(
      '<' => $this->t('Is less than'),
      '<=' => $this->t('Is less than or equal to'),
      '=' => $this->t('Is equal to'),
      '<>' => $this->t('Is not equal to'),
      '>=' => $this->t('Is greater than or equal to'),
      '>' => $this->t('Is greater than'),
      'empty' => $this->t('Is empty'),
      'not empty' => $this->t('Is not empty'),
    );
  }

  /**
   * Provide a form for setting the filter value.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    while (is_array($this->value) && count($this->value) < 2) {
      $this->value = $this->value ? reset($this->value) : NULL;
    }
    $form['value'] = array(
      '#type' => 'textfield',
      '#title' => empty($form_state['exposed']) ? $this->t('Value') : '',
      '#size' => 30,
      '#default_value' => isset($this->value) ? $this->value : '',
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
   * Display the filter on the administrative summary
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

    return String::checkPlain((string) $this->operator) . ' ' . String::checkPlain((string) $this->value);
  }

  /**
   * Add this filter to the query.
   */
  public function query() {
    if ($this->operator === 'empty') {
      $this->query->condition($this->realField, NULL, '=', $this->options['group']);
    }
    elseif ($this->operator === 'not empty') {
      $this->query->condition($this->realField, NULL, '<>', $this->options['group']);
    }
    else {
      while (is_array($this->value)) {
        $this->value = $this->value ? reset($this->value) : NULL;
      }
      if (strlen($this->value) > 0) {
        $this->query->condition($this->realField, $this->value, $this->operator, $this->options['group']);
      }
    }
  }

}
