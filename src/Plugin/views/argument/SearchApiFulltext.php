<?php

/**
 * @file
 * Contains SearchApiViewsHandlerArgumentFulltext.
 */

namespace Drupal\search_api\Plugin\views\argument;

/**
 * Views argument handler class for handling fulltext fields.
 *
 * @ViewsArgument("search_api_fulltext")
 */

class SearchApiFulltext extends SearchApiArgument {

  /**
   * Specify the options this filter uses.
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['fields'] = array('default' => array());
    $options['conjunction'] = array('default' => 'AND');
    return $options;
  }

  /**
   * Extend the options form a bit.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['help']['#markup'] = $this->t('Note: You can change how search keys are parsed under "Advanced" > "Query settings".');

    $fields = $this->getFulltextFields();
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
      $form['conjunction'] = array(
        '#title' => $this->t('Operator'),
        '#description' => $this->t('Determines how multiple keywords entered for the search will be combined.'),
        '#type' => 'radios',
        '#options' => array(
          'AND' => $this->t('Contains all of these words'),
          'OR' => $this->t('Contains any of these words'),
        ),
        '#default_value' => $this->options['conjunction'],
      );

    }
    else {
      $form['fields'] = array(
        '#type' => 'value',
        '#value' => array(),
      );
    }
  }

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    if ($this->options['fields']) {
      $this->query->fields($this->options['fields']);
    }
    if ($this->options['conjunction'] != 'AND') {
      $this->query->setOption('conjunction', $this->options['conjunction']);
    }

    $old = $this->query->getOriginalKeys();
    $this->query->keys($this->argument);
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
    $index = $this->query->getIndex();

    $fields_info = $index->getFields();
    foreach ($index->getFulltextFields() as $field_id) {
      $fields[$field_id] = $fields_info[$field_id]->getPrefixedLabel();
    }

    return $fields;
  }

}
