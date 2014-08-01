<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilterBoolean.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Views filter handler class for handling fulltext fields.
 *
 * @ViewsFilter("search_api_boolean")
 */
class SearchApiFilterBoolean extends SearchApiFilter {

  /**
   * Provide a list of options for the operator form.
   */
  public function operatorOptions() {
    return array();
  }

  /**
   * Provide a form for setting the filter value.
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    while (is_array($this->value)) {
      $this->value = $this->value ? array_shift($this->value) : NULL;
    }
    $form['value'] = array(
      '#type' => 'select',
      '#title' => empty($form_state['exposed']) ? $this->t('Value') : '',
      '#options' => array(1 => $this->t('True'), 0 => $this->t('False')),
      '#default_value' => isset($this->value) ? $this->value : '',
    );
  }

}
