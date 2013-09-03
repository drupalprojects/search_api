<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\AddAggregation.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;
use Drupal\search_api\Annotation\SearchApiProcessor;

/**
 * Provides a processor for adding aggregations of existing fields to the index.
 *
 * @SearchApiProcessor(
 *   id = "search_api_add_aggregation",
 *   name = @Translation("Aggregated fields"),
 *   description = @Translation("Gives you the ability to define additional fields, containing data from one or more other fields."),
 *   weight = -10
 * )
 */
class AddAggregation extends ProcessorPluginBase {

  /**
   * Whether there are unsaved changes for this processor's configuration.
   *
   * @var bool
   */
  protected $changes = FALSE;

  /**
   * The type of reduction used for a certain aggregated field.
   *
   * Used in reduce() to decide how to combine the array values.
   *
   * @var string
   */
  protected $reductionType;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';

    $fields = $this->index->getFields(FALSE);
    $field_options = array();
    foreach ($fields as $name => $field) {
      $field_options[$name] = $field['name'];
    }
    $additional = empty($this->options['fields']) ? array() : $this->options['fields'];

    $types = $this->getTypes();
    $type_descriptions = $this->getTypes('description');
    $tmp = array();
    foreach ($types as $type => $name) {
      $tmp[$type] = array(
        '#type' => 'item',
        '#description' => $type_descriptions[$type],
      );
    }
    $type_descriptions = $tmp;

    $form['#id'] = 'edit-callbacks-search-api-alter-add-aggregation-settings';
    $form['description'] = array(
      '#markup' => t('<p>This data alteration lets you define additional fields that will be added to this index. ' .
        'Each of these new fields will be an aggregation of one or more existing fields.</p>' .
        '<p>To add a new aggregated field, click the "Add new field" button and then fill out the form.</p>' .
        '<p>To remove a previously defined field, click the "Remove field" button.</p>' .
        '<p>You can also change the names or contained fields of existing aggregated fields.</p>'),
    );
    $form['fields']['#prefix'] = '<div id="search-api-alter-add-aggregation-field-settings">';
    $form['fields']['#suffix'] = '</div>';
    if (isset($this->changes)) {
      $form['fields']['#prefix'] .= '<div class="messages warning">All changes in the form will not be saved until the <em>Save configuration</em> button at the form bottom is clicked.</div>';
    }
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
      $form['fields'][$name]['type'] = array(
        '#type' => 'select',
        '#title' => t('Aggregation type'),
        '#options' => $types,
        '#default_value' => $field['type'],
        '#required' => TRUE,
      );
      $form['fields'][$name]['type_descriptions'] = $type_descriptions;
      foreach (array_keys($types) as $type) {
        $form['fields'][$name]['type_descriptions'][$type]['#states']['visible'][':input[name="callbacks[search_api_alter_add_aggregation][settings][fields][' . $name . '][type]"]']['value'] = $type;
      }
      $form['fields'][$name]['fields'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Contained fields'),
        '#options' => $field_options,
        '#default_value' => drupal_map_assoc($field['fields']),
        '#attributes' => array('class' => array('search-api-alter-add-aggregation-fields')),
        '#required' => TRUE,
      );
      $form['fields'][$name]['actions'] = array(
        '#type' => 'actions',
        'remove' => array(
          '#type' => 'submit',
          '#value' => t('Remove field'),
          '#submit' => array('_search_api_add_aggregation_field_submit'),
          '#limit_validation_errors' => array(),
          '#name' => 'search_api_add_aggregation_remove_' . $name,
          '#ajax' => array(
            'callback' => '_search_api_add_aggregation_field_ajax',
            'wrapper' => 'search-api-alter-add-aggregation-field-settings',
          ),
        ),
      );
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['add_field'] = array(
      '#type' => 'submit',
      '#value' => t('Add new field'),
      '#submit' => array('_search_api_add_aggregation_field_submit'),
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'callback' => '_search_api_add_aggregation_field_ajax',
        'wrapper' => 'search-api-alter-add-aggregation-field-settings',
      ),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    $values = $form_state['values'];
    unset($values['actions']);
    if (empty($values['fields'])) {
      return;
    }
    foreach ($values['fields'] as $name => $field) {
      $fields = $values['fields'][$name]['fields'] = array_values(array_filter($field['fields']));
      unset($values['fields'][$name]['actions']);
      if ($field['name'] && !$fields) {
        form_error($form['fields'][$name]['fields'], t('You have to select at least one field to aggregate. If you want to remove an aggregated field, please delete its name.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $values = $form_state['values'];
    if (empty($values['fields'])) {
      return;
    }
    $index_fields = $this->index->getFields(FALSE);
    foreach ($values['fields'] as $name => $field) {
      if (!$field['name']) {
        unset($values['fields'][$name]);
      }
      else {
        $values['fields'][$name]['description'] = $this->fieldDescription($field, $index_fields);
      }
    }
    $this->options = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    if (!$items) {
      return;
    }
    if (isset($this->options['fields'])) {
      $types = $this->getTypes('type');
      foreach ($items as $item) {
        $wrapper = $this->index->entityWrapper($item);
        foreach ($this->options['fields'] as $name => $field) {
          if ($field['name']) {
            $required_fields = array();
            foreach ($field['fields'] as $f) {
              if (!isset($required_fields[$f])) {
                $required_fields[$f]['type'] = $types[$field['type']];
              }
            }
            $fields = search_api_extract_fields($wrapper, $required_fields);
            $values = array();
            foreach ($fields as $f) {
              if (isset($f['value'])) {
                $values[] = $f['value'];
              }
            }
            $values = $this->flattenArray($values);

            $this->reductionType = $field['type'];
            $item->$name = array_reduce($values, array($this, 'reduce'), NULL);
            if ($field['type'] == 'count' && !$item->$name) {
              $item->$name = 0;
            }
          }
        }
      }
    }
  }

  /**
   * Combines two values of an array to a single one.
   *
   * Used as the callback function for array_reduce() in alterItems().
   *
   * @param mixed $a
   *   The first value.
   * @param mixed $b
   *   The second value.
   *
   * @return mixed
   *   A combined value.
   */
  public function reduce($a, $b) {
    switch ($this->reductionType) {
      case 'fulltext':
        return isset($a) ? $a . "\n\n" . $b : $b;
      case 'sum':
        return $a + $b;
      case 'count':
        return $a + 1;
      case 'max':
        return isset($a) ? max($a, $b) : $b;
      case 'min':
        return isset($a) ? min($a, $b) : $b;
      case 'first':
        return isset($a) ? $a : $b;
    }
  }

  /**
   * Flattens a multi-dimensional array.
   *
   * @param array $data
   *   The array to flatten.
   *
   * @return array
   *   A one-dimensional array.
   */
  protected function flattenArray(array $data) {
    $ret = array();
    foreach ($data as $item) {
      if (!isset($item)) {
        continue;
      }
      if (is_scalar($item)) {
        $ret[] = $item;
      }
      else {
        $ret = array_merge($ret, $this->flattenArray($item));
      }
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    $types = $this->getTypes('type');
    $ret = array();
    if (isset($this->options['fields'])) {
      foreach ($this->options['fields'] as $name => $field) {
        $ret[$name] = array(
          'label' => $field['name'],
          'description' => empty($field['description']) ? '' : $field['description'],
          'type' => $types[$field['type']],
        );
      }
    }
    return $ret;
  }

  /**
   * Creates a description for an aggregated field.
   *
   * @param array $field
   *   The configuration of the aggregated fields.
   * @param array $index_fields
   *   Information about all fields in the index.
   *
   * @return string
   */
  protected function fieldDescription(array $field, array $index_fields) {
    $fields = array();
    foreach ($field['fields'] as $f) {
      $fields[] = isset($index_fields[$f]) ? $index_fields[$f]['name'] : $f;
    }
    $type = $this->getTypes();
    $type = $type[$field['type']];
    return t('A @type aggregation of the following fields: @fields.', array('@type' => $type, '@fields' => implode(', ', $fields)));
  }

  /**
   * Retrieves information about all available aggregation types.
   *
   * @param string $info
   *   (optional) One of "name", "type" or "description", to indicate what
   *   values should be returned for the types.
   *
   * @return array
   *   The requested information about the field.
   */
  protected function getTypes($info = 'name') {
    switch ($info) {
      case 'name':
        return array(
          'fulltext' => t('Fulltext'),
          'sum' => t('Sum'),
          'count' => t('Count'),
          'max' => t('Maximum'),
          'min' => t('Minimum'),
          'first' => t('First'),
        );
      case 'type':
        return array(
          'fulltext' => 'text',
          'sum' => 'integer',
          'count' => 'integer',
          'max' => 'integer',
          'min' => 'integer',
          'first' => 'string',
        );
      case 'description':
        return array(
          'fulltext' => t('The Fulltext aggregation concatenates the text data of all contained fields.'),
          'sum' => t('The Sum aggregation adds the values of all contained fields numerically.'),
          'count' => t('The Count aggregation takes the total number of contained field values as the aggregated field value.'),
          'max' => t('The Maximum aggregation computes the numerically largest contained field value.'),
          'min' => t('The Minimum aggregation computes the numerically smallest contained field value.'),
          'first' => t('The First aggregation will simply keep the first encountered field value. This is helpful foremost when you know that a list field will only have a single value.'),
        );
    }
  }

  /**
   * Submit callback for buttons in the processor's configuration form.
   */
  public function formButtonSubmit(array $form, array &$form_state) {
    $button_name = $form_state['triggering_element']['#name'];
    if ($button_name == 'op') {
      for ($i = 1; isset($this->options['fields']['search_api_aggregation_' . $i]); ++$i) {
      }
      $this->options['fields']['search_api_aggregation_' . $i] = array(
        'name' => '',
        'type' => 'fulltext',
        'fields' => array(),
      );
    }
    else {
      $field = substr($button_name, 34);
      unset($this->options['fields'][$field]);
    }
    $form_state['rebuild'] = TRUE;
    $this->changes = TRUE;
  }

}
