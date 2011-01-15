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
    foreach ($additional as $name => $field) {
      if (!$field['name']) {
        unset($additional[$name]);
        if (!isset($first_free_name)) {
          $first_free_name = $name;
        }
      }
    }
    $additional[isset($first_free_name) ? $first_free_name : 'search_api_fulltext_' . (count($additional) + 1)] = array(
      'name' => '',
      'fields' => array(),
    );
    $form['description'] = array(
      '#markup' => '<p>' . t('Change the name or aggregated fields of one of the additional fulltext fields below, or add a new one by filling out the "New field" form. By deleting a name, you can remove previously added fields.') . '</p>',
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
        '#title' => t('Field name'),
        '#default_value' => $field['name'],
      );
      $form['fields'][$name]['fields'] = array(
        '#type' => 'select',
        '#title' => t('Contained fields'),
        '#options' => $field_options,
        '#size' => min(count($field_options), 5),
        '#multiple' => TRUE,
        '#default_value' => $field['fields'],
      );
    }
    return $form;
  }

  public function alterItems(array &$items) {
    if (!$items) {
      return;
    }
    $required_fields = array();
    foreach ($this->options['fields'] as $name => $field) {
      if ($field['name']) {
        foreach ($field['fields'] as $f) {
          if (!isset($required_fields[$f])) {
            $required_fields[$f]['type'] = 'text';
          }
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
    foreach ($this->options['fields'] as $name => $field) {
      if ($field['name']) {
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
