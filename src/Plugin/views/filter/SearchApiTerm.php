<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilterTaxonomyTerm.
 */

namespace Drupal\search_api\Plugin\views\filter;

/**
 * Views filter handler class for taxonomy term entities.
 *
 * Based on views_handler_filter_term_node_tid.
 *
 * @ViewsFilter("search_api_term")
 */
class SearchApiTerm extends SearchApiFilterEntityBase {

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions() {
    return !empty($this->definition['vocabulary']);
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = array('default' => !empty($this->definition['vocabulary']) ? 'textfield' : 'select');
    $options['hierarchy'] = array('default' => 0);
    $options['error_message'] = array('default' => TRUE, 'bool' => TRUE);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, &$form_state) {
    $form['type'] = array(
      '#type' => 'radios',
      '#title' => t('Selection type'),
      '#options' => array('select' => t('Dropdown'), 'textfield' => t('Autocomplete')),
      '#default_value' => $this->options['type'],
    );

    $form['hierarchy'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show hierarchy in dropdown'),
      '#default_value' => !empty($this->options['hierarchy']),
    );
    $form['hierarchy']['#states']['visible'][':input[name="options[type]"]']['value'] = 'select';
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, &$form_state) {
    parent::valueForm($form, $form_state);

    if (!empty($this->definition['vocabulary'])) {
      $vocabulary = entity_load('taxonomy_vocabulary', $this->definition['vocabulary']);
      $title = t('Select terms from vocabulary @voc', array('@voc' => $vocabulary->label()));
    }
    else {
      $vocabulary = FALSE;
      $title = t('Select terms');
    }
    $form['value']['#title'] = $title;

    if ($vocabulary && $this->options['type'] == 'textfield') {
      $form['value']['#autocomplete_path'] = 'admin/views/ajax/autocomplete/taxonomy/' . $vocabulary->id();
    }
    else {
      if ($vocabulary && !empty($this->options['hierarchy'])) {
        $tree = taxonomy_get_tree($vocabulary->id());
        $options = array();

        if ($tree) {
          foreach ($tree as $term) {
            $choice = new \stdClass();
            $choice->option = array($term->tid => str_repeat('-', $term->depth) . $term->name);
            $options[] = $choice;
          }
        }
      }
      else {
        $options = array();
        $query = db_select('taxonomy_term_data', 'td');
        $query->innerJoin('taxonomy_vocabulary', 'tv', 'td.vid = tv.vid');
        $query->fields('td');
        $query->orderby('tv.weight');
        $query->orderby('tv.name');
        $query->orderby('td.weight');
        $query->orderby('td.name');
        $query->addTag('term_access');
        if ($vocabulary) {
          $query->condition('tv.vid', $vocabulary->id());
        }
        $result = $query->execute();
        foreach ($result as $term) {
          $options[$term->tid] = $term->name;
        }
      }

      $default_value = (array) $this->value;

      if (!empty($form_state['exposed'])) {
        $identifier = $this->options['expose']['identifier'];

        if (!empty($this->options['expose']['reduce'])) {
          $options = $this->reduceValueOptions($options);

          if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
            $default_value = array();
          }
        }

        if (empty($this->options['expose']['multiple'])) {
          if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($options);
            $default_value = array_shift($keys);
          }
          // Due to #1464174 there is a chance that array('') was saved in the
          // admin ui. Let's choose a safe default value.
          elseif ($default_value == array('')) {
            $default_value = 'All';
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }
      $form['value']['#type'] = 'select';
      $form['value']['#multiple'] = TRUE;
      $form['value']['#options'] = $options;
      $form['value']['#size'] = min(9, count($options));
      $form['value']['#default_value'] = $default_value;

      if (!empty($form_state['exposed']) && isset($identifier) && !isset($form_state['input'][$identifier])) {
        $form_state['input'][$identifier] = $default_value;
      }
    }
  }

  /**
   * Reduces the available exposed options according to the selection.
   */
  protected function reduceValueOptions(array $options) {
    foreach ($options as $id => $option) {
      if (empty($this->options['value'][$id])) {
        unset($options[$id]);
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function valueValidate($form, &$form_state) {
    // We only validate if they've chosen the text field style.
    if ($this->options['type'] != 'textfield') {
      return;
    }

    parent::valueValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated.
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validated_exposed_input = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$this->options['expose']['required'] && empty($this->validated_exposed_input)) {
      return FALSE;
    }

    return parent::acceptExposedInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, &$form_state) {
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return;
    }

    // We only validate if they've chosen the text field style.
    if ($this->options['type'] != 'textfield') {
      $input = $form_state['values'][$this->options['expose']['identifier']];
      if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
        $input = $this->options['group_info']['group_items'][$input]['value'];
      }

      if ($input != 'All')  {
        $this->validated_exposed_input = (array) $input;
      }
      return;
    }

    parent::validateExposed($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function validateEntityStrings(array &$form, array $values) {
    if (empty($values)) {
      return array();
    }

    $tids = array();
    $names = array();
    $missing = array();
    foreach ($values as $value) {
      $missing[strtolower($value)] = TRUE;
      $names[] = $value;
    }

    if (!$names) {
      return FALSE;
    }

    $query = db_select('taxonomy_term_data', 'td');
    $query->innerJoin('taxonomy_vocabulary', 'tv', 'td.vid = tv.vid');
    $query->fields('td');
    $query->condition('td.name', $names);
    if (!empty($this->definition['vocabulary'])) {
      $query->condition('tv.machine_name', $this->definition['vocabulary']);
    }
    $query->addTag('term_access');
    $result = $query->execute();
    foreach ($result as $term) {
      unset($missing[strtolower($term->name)]);
      $tids[] = $term->tid;
    }

    if ($missing) {
      if (!empty($this->options['error_message'])) {
        form_error($form, format_plural(count($missing), 'Unable to find term: @terms', 'Unable to find terms: @terms', array('@terms' => implode(', ', array_keys($missing)))));
      }
      else {
        // Add a bogus TID which will show an empty result for a positive filter
        // and be ignored for an excluding one.
        $tids[] = 0;
      }
    }

    return $tids;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, &$form_state) {
    parent::buildExposedForm($form, $form_state);
    if ($this->options['type'] != 'select') {
      unset($form['expose']['reduce']);
    }
    $form['error_message'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display error message'),
      '#description' => t('Display an error message if one of the entered terms could not be found.'),
      '#default_value' => !empty($this->options['error_message']),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function idsToStrings(array $ids) {
    return implode(', ', db_select('taxonomy_term_data', 'td')
      ->fields('td', array('name'))
      ->condition('td.tid', array_filter($ids))
      ->execute()
      ->fetchCol());
  }

}
