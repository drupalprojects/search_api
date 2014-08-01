<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilterFulltext.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;

/**
 * Views filter handler class for handling fulltext fields.
 *
 * @ViewsFilter("search_api_fulltext")
 */
class SearchApiFulltext extends SearchApiFilterText {

  /**
   * Displays the operator form, adding a description.
   */
  public function showOperatorForm(&$form, FormStateInterface $form_state) {
    $this->operatorForm($form, $form_state);
    $form['operator']['#description'] = $this->t('This operator is only useful when using \'Search keys\'.');
  }

  /**
   * Provide a list of options for the operator form.
   */
  public function operatorOptions() {
    return array(
      'AND' => $this->t('Contains all of these words'),
      'OR' => $this->t('Contains any of these words'),
      'NOT' => $this->t('Contains none of these words'),
    );
  }

  /**
   * Specify the options this filter uses.
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['operator']['default'] = 'AND';

    $options['mode'] = array('default' => 'keys');
    $options['min_length'] = array('default' => '');
    $options['fields'] = array('default' => array());

    return $options;
  }

  /**
   * Extend the options form a bit.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['mode'] = array(
      '#title' => $this->t('Use as'),
      '#type' => 'radios',
      '#options' => array(
        'keys' => $this->t('Search keys – multiple words will be split and the filter will influence relevance. You can change how search keys are parsed under "Advanced" > "Query settings".'),
        'filter' => $this->t("Search filter – use as a single phrase that restricts the result set but doesn't influence relevance."),
      ),
      '#default_value' => $this->options['mode'],
    );

    $fields = $this->getFulltextFields();
//    $fields = array();
    if (!empty($fields)) {
      $form['fields'] = array(
        '#type' => 'select',
        '#title' => $this->t('Searched fields'),
        '#description' => $this->t('Select the fields that will be searched. If no fields are selected, all available fulltext fields will be searched.'),
        '#options' => $fields,
        '#size' => min(4, count($fields)),
        '#multiple' => TRUE,
        '#default_value' => $this->options['fields'],
      );
    }
    else {
      $form['fields'] = array(
        '#type' => 'value',
        '#value' => array(),
      );
    }
    if (isset($form['expose'])) {
      $form['expose']['#weight'] = -5;
    }

    $form['min_length'] = array(
      '#title' => $this->t('Minimum keyword length'),
      '#description' => $this->t('Minimum length of each word in the search keys. Leave empty to allow all words.'),
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => $this->options['min_length'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    // Only validate exposed input.
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return;
    }

    // We only need to validate if there is a minimum word length set.
    if ($this->options['min_length'] < 2) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $input = &$form_state['values'][$identifier];

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->operator = $this->options['group_info']['group_items'][$input]['operator'];
      $input = &$this->options['group_info']['group_items'][$input]['value'];
    }

    // If there is no input, we're fine.
    if (!trim($input)) {
      return;
    }

    $words = preg_split('/\s+/', $input);
    foreach ($words as $i => $word) {
      if (Unicode::strlen($word) < $this->options['min_length']) {
        unset($words[$i]);
      }
    }
    if (!$words) {
      $vars['@count'] = $this->options['min_length'];
      $msg = $this->t('You must include at least one positive keyword with @count characters or more.', $vars);
      \Drupal::formBuilder()->setError($form[$identifier], $form_state, $msg);
    }
    $input = implode(' ', $words);
  }

  /**
   * Add this filter to the query.
   */
  public function query() {
    while (is_array($this->value)) {
      $this->value = $this->value ? reset($this->value) : '';
    }
    // Catch empty strings entered by the user, but not "0".
    if ($this->value === '') {
      return;
    }
    $fields = $this->options['fields'];
    $fields = $fields ? $fields : array_keys($this->getFulltextFields());

    // If something already specifically set different fields, we silently fall
    // back to mere filtering.
    $filter = $this->options['mode'] == 'filter';
    if (!$filter) {
      $old = $this->query->getFields();
      $filter = $old && (array_diff($old, $fields) || array_diff($fields, $old));
    }

    if ($filter) {
      $filter = $this->query->createFilter('OR');
      foreach ($fields as $field) {
        $filter->condition($field, $this->value, $this->operator);
      }
      $this->query->filter($filter);
      return;
    }

    // If the operator was set to OR or NOT, set OR as the conjunction. (It is
    // also set for NOT since otherwise it would be "not all of these words".)
    if ($this->operator != 'AND') {
      $this->query->setOption('conjunction', $this->operator);
    }

    $this->query->fields($fields);
    $old = $this->query->getOriginalKeys();
    $this->query->keys($this->value);
    if ($this->operator == 'NOT') {
      $keys = &$this->query->getKeys();
      if (is_array($keys)) {
        $keys['#negation'] = TRUE;
      }
      else {
        // We can't know how negation is expressed in the server's syntax.
      }
    }
    if ($old) {
      $keys = &$this->query->getKeys();
      if (is_array($keys)) {
        $keys[] = $old;
      }
      elseif (is_array($old)) {
        // We don't support such nonsense.
      }
      else {
        $keys = "($old) ($keys)";
      }
    }
  }

  /**
   * Helper method to get an option list of all available fulltext fields.
   */
  protected function getFulltextFields() {
    $fields = array();
    $index = entity_load('search_api_index', substr($this->table, 17));

    $fields_info = $index->getFields();
    foreach ($index->getFulltextFields() as $field_id) {
      $fields[$field_id] = $fields_info[$field_id]->getPrefixedLabel();
    }

    return $fields;
  }

}
