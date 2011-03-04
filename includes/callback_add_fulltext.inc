<?php

/**
 * Search API data alteration callback that adds an URL field for all items.
 */
class SearchApiAlterAddFulltext extends SearchApiAbstractAlterCallback {

  public function configurationForm() {
    // Workaround for http://drupal.org/node/1015798
    $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';

    $fields = empty($this->index->options['fields']) ? _search_api_admin_get_fields($this->index, $this->index->entityWrapper()) : $this->index->options;
    $fields = $fields['fields'];
    $field_options = array();
    foreach ($fields as $name => $field) {
      $field_options[$name] = $field['name'];
    }
    $additional = empty($this->options['fields']) ? array() : $this->options['fields'];

    $form['#id'] = 'edit-callbacks-search-api-alter-add-fulltext-settings';
    $form['description'] = array(
      '#markup' => t('<p>This data alteration lets you define additional fields that will be added to this index. ' .
        'Each of these new fields will be a fulltext aggregation of one or more existing fields.</p>' .
        '<p>To add a new aggregated field, click the "Add new field" button and then fill out the form.</p>' .
        '<p>To remove a previously defined field, click the "Remove field" button.</p>' .
        '<p>You can also change the names or contained fields of existing aggregated fields.</p>'),
    );
    if (isset($this->changes)) {
      $form['description']['#markup'] .= '<div class="messages warning">All changes in the form will not be saved until the <em>Save configuration</em> button at the form bottom is clicked.</div>';
    }
    $form['fields']['#prefix'] = '<div id="search-api-alter-add-fulltext-field-settings">';
    $form['fields']['#suffix'] = '</div>';
    foreach ($additional as $name => $field) {
      $form['fields'][$name] = array(
        '#type' => 'fieldset',
        '#title' => $field['name'] ? $field['name'] : t('New field'),
        '#collapsible' => TRUE,
        '#collapsed' => (boolean) $field['name'],
      );
      $form['fields'][$name]['name'] = array(
        '#type' => 'textfield',
        '#title' => t('New field name'),
        '#default_value' => $field['name'],
        '#required' => TRUE,
      );
      $form['fields'][$name]['fields'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Contained fields'),
        '#options' => $field_options,
        '#default_value' => $field['fields'],
        '#attributes' => array('class' => array('search-api-alter-add-fulltext-fields')),
        '#required' => TRUE,
      );
      $form['fields'][$name]['actions'] = array(
        '#type' => 'actions',
        'remove' => array(
          '#type' => 'submit',
          '#value' => t('Remove field'),
          '#submit' => array('_search_api_add_fulltext_field_submit'),
          '#limit_validation_errors' => array(),
          '#name' => 'search_api_add_fulltext_remove_' . $name,
          '#ajax' => array(
            'callback' => '_search_api_add_fulltext_field_ajax',
            'wrapper' => 'search-api-alter-add-fulltext-field-settings',
          ),
        ),
      );
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['add_field'] = array(
      '#type' => 'submit',
      '#value' => t('Add new field'),
      '#submit' => array('_search_api_add_fulltext_field_submit'),
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'callback' => '_search_api_add_fulltext_field_ajax',
        'wrapper' => 'search-api-alter-add-fulltext-field-settings',
      ),
    );
    return $form;
  }

  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    foreach ($values['fields'] as $name => $field) {
      if ($field['name'] && !$field['fields']) {
        form_error($form['fields'][$name]['fields'], t('You have to select at least one field to aggregate. If you want to remove an aggregated field, please delete its name.'));
      }
    }
  }

  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    foreach ($values['fields'] as $name => $field) {
      if (!$field['name']) {
        unset($values['fields'][$name]);
      }
    }
    $this->options = $values;
    return $values;
  }

  public function alterItems(array &$items) {
    if (!$items) {
      return;
    }
    $required_fields = array();
    if (isset($this->options['fields'])) {
      foreach ($this->options['fields'] as $name => $field) {
        foreach ($field['fields'] as $f) {
          if (!isset($required_fields[$f])) {
            $required_fields[$f]['type'] = 'text';
          }
        }
      }
      foreach ($items as $item) {
        $wrapper = $this->index->entityWrapper($item);
        $fields = search_api_extract_fields($wrapper, $required_fields);
        foreach ($this->options['fields'] as $name => $field) {
          if ($field['name']) {
            $item->$name = '';
            foreach ($field['fields'] as $f) {
              if (isset($fields[$f]['value'])) {
                $item->$name .= ($item->$name ? "\n\n" : '') . $this->collapseArray($fields[$f]['value']);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Helper method for collapsing array values into a string.
   */
  protected function collapseArray($data) {
    if (!is_array($data)) {
      return $data;
    }
    if (!$data) {
      return '';
    }
    $ret = array();
    foreach ($data as $item) {
      $item = $this->collapseArray($item);
      if ($item) {
        $ret[] = $item;
      }
    }
    return implode("\n", $ret);
  }

  public function propertyInfo() {
    $ret = array();
    if (isset($this->options['fields'])) {
      foreach ($this->options['fields'] as $name => $field) {
        $ret[$name] = array(
          'label' => $field['name'],
          'description' => $this->fieldDescription($field),
          'type' => 'text',
        );
      }
    }
    return $ret;
  }

  /**
   * Helper method for creating a field description.
   */
  protected function fieldDescription(array $field) {
    $fields = array();
    foreach ($field['fields'] as $f) {
      if ($f !== 0) {
        $fields[] = isset($this->index->options['fields'][$f]) ? $this->index->options['fields'][$f]['name'] : $f;
      }
    }
    return t('A fulltext aggregation of the following fields: @fields.', array('@fields' => implode(', ', $fields)));
  }

  /**
   * Submit helper callback for buttons in the callback's configuration form.
   */
  public function formButtonSubmit(array $form, array &$form_state) {
    $button_name = $form_state['triggering_element']['#name'];
    if ($button_name == 'op') {
      for ($i = 1; isset($this->options['fields']['search_api_fulltext_' . $i]); ++$i) {
      }
      $this->options['fields']['search_api_fulltext_' . $i] = array(
        'name' => '',
        'fields' => $form_state['index']->getFulltextFields(),
      );
    }
    else {
      $field = substr($button_name, 31);
      unset($this->options['fields'][$field]);
    }
    $form_state['rebuild'] = TRUE;
    $this->changes = TRUE;
  }

}

/**
 * Submit function for buttons in the callback's configuration form.
 */
function _search_api_add_fulltext_field_submit(array $form, array &$form_state) {
  $form_state['callbacks']['search_api_alter_add_fulltext']->formButtonSubmit($form, $form_state);
}

/**
 * AJAX submit function for buttons in the callback's configuration form.
 */
function _search_api_add_fulltext_field_ajax(array $form, array &$form_state) {
  return $form['callbacks']['settings']['search_api_alter_add_fulltext']['fields'];
}
