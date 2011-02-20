<?php
// $Id$

/**
 * Search API data alteration callback that adds an URL field for all items.
 */
class SearchApiAlterAddFulltext extends SearchApiAbstractAlterCallback {

  public function configurationForm() {
    // Workaround for http://drupal.org/node/1015798
    drupal_add_css(drupal_get_path('module', 'search_api') . '/search_api.admin.css');

    $fields = empty($this->index->options['fields']) ? _search_api_admin_get_fields($this->index, $this->index->entityWrapper()) : $this->index->options;
    $fields = $fields['fields'];
    $field_options = array();
    foreach ($fields as $name => $field) {
      $field_options[$name] = $field['name'];
    }
    $additional = empty($this->options['fields']) ? array() : $this->options['fields'];
    for ($i = 1; isset($this->options['fields']['search_api_fulltext_' . $i]); ++$i) {
    }
    $additional['search_api_fulltext_' . $i] = array(
      'name' => '',
      'fields' => $this->index->getFulltextFields(),
    );
    $form['description'] = array(
      '#markup' => t('<p>This data alteration lets you define additional fields that will be added to this index. ' .
        'Each of these new fields will be a fulltext aggregation of one or more existing fields.</p>' .
        '<p>To add a new aggregated field, fill out the "New field" form below.</p>' .
        '<p>To remove a previously defined field, simply delete its name.</p>' .
        '<p>You can also change the names or contained fields of existing aggregated fields.</p>'),
    );
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
      );
      $form['fields'][$name]['fields'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Contained fields'),
        '#options' => $field_options,
        '#default_value' => $field['fields'],
        '#attributes' => array('class' => array('search-api-alter-add-fulltext-fields')),
      );
    }
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
      $fields[] = isset($this->index->options['fields'][$f]) ? $this->index->options['fields'][$f]['name'] : $f;
    }
    return t('A fulltext aggregation of the following fields: @fields.', array('@fields' => implode(', ', $fields)));
  }

}
