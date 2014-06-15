<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\AddAggregation.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "aggregated_field",
 *   label = @Translation("Aggregated Field Processor"),
 *   description = @Translation("Create aggregate fields to be additionally indexed.")
 * )
 */
class AggregatedField extends ProcessorPluginBase {

  protected $reductionType;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['description'] = array(
      '#markup' => t('This data alteration lets you define additional fields that will be added to this index. ' .
        'Each of these new fields will be an aggregation of one or more existing fields.</br>' .
        'To add a new aggregated field, click the "Add new field" button and then fill out the form.</br>' .
        'To remove a previously defined field, click the "Remove field" button. </br>' .
        'You can also change the names or contained fields of existing aggregated fields.'),
    );

    $this->buildFieldsForm($form, $form_state);

    $form['actions']['#type'] = 'actions';
    $form['actions'] = array(
      '#type' => 'actions',
      'add' => array(
        '#type' => 'submit',
        '#value' => t('Add new Field'),
        '#submit' => array(array($this, 'submitAjaxFieldButton')),
        '#limit_validation_errors' => array(),
        '#name' => 'add_aggregation_field',
        '#ajax' => array(
          'callback' => array($this, 'buildAjaxAddFieldButton'),
          'wrapper' => 'search-api-alter-add-aggregation-field-settings',
        ),
      ),
    );

    return $form;
  }

  public function buildFieldsForm(array &$form, array &$form_state) {
    // Check if we need to add a new form.
    if (isset($form_state['triggering_element']['#name'])) {
      $button_name = $form_state['triggering_element']['#name'];
      if ($button_name == 'add_aggregation_field') {
        for ($i = 1; isset($form_state['fields']['search_api_aggregation_' . $i]); ++$i) {
        }
        $form_state['fields']['search_api_aggregation_' . $i] = array(
          'label' => '',
          'type' => 'fulltext',
          'fields' => array(),
        );
      }
      else {
        // Get the field id from the button
        $field_id = substr($button_name, 25);
        unset($form_state['fields'][$field_id]);
      }
    }

    // Get the aggregated fields.
    $aggregated_fields = $this->getAggregatedFields($form, $form_state);
    // Get index type descriptions.
    $type_descriptions = $this->getTypeDescriptions();
    $types = $this->getTypes();

    // Get the available fields for this index.
    $fields = $this->index->getFields(FALSE);
    $field_options = array();
    $field_properties = array();

    // Annotate them so we can show them cleanly in the UI.
    /** @var \Drupal\search_api\Item\FieldInterface[] $fields */
    foreach ($fields as $field_id => $field) {
      $field_options[$field_id] = $field->getLabel();
      $field_properties[$field_id] = array(
        '#attributes' => array('title' => $field_id),
        '#description' => $field->getDescription(),
      );
    }

    // Container for the aggregated field configurations
    $form['fields'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-alter-add-aggregation-field-settings',
      ),
      '#weight' => 7,
      '#tree' => TRUE,
    );


    foreach ($aggregated_fields as $field_id => $field) {
      $form['fields'][$field_id] = array(
        '#type' => 'fieldset',
        '#title' => isset($field['label']) ? $field['label'] : t('New field'),
        '#collapsible' => TRUE,
        '#collapsed' => isset($field['label']),
      );
      $form['fields'][$field_id]['label'] = array(
        '#type' => 'textfield',
        '#title' => t('New field name'),
        '#default_value' => isset($field['label']) ? $field['label'] : t('New field'),
        '#required' => TRUE,
      );
      $form['fields'][$field_id]['type'] = array(
        '#type' => 'select',
        '#title' => t('Aggregation type'),
        '#options' => $types,
        '#default_value' => isset($field['type']) ? $field['type'] : 'union',
        '#required' => TRUE,
      );

      $form['fields'][$field_id]['type_descriptions'] = $type_descriptions;
      foreach (array_keys($types) as $type) {
        $form['fields'][$field_id]['type_descriptions'][$type]['#states']['visible'][':input[name="callbacks[search_api_alter_aggregated_field][settings][fields][' . $field_id . '][type]"]']['value'] = $type;
      }

      $form['fields'][$field_id]['fields'] = array_merge($field_properties, array(
        '#type' => 'checkboxes',
        '#title' => t('Contained fields'),
        '#options' => $field_options,
        '#default_value' => array_combine($field['fields'], $field['fields']),
        '#attributes' => array('class' => array('search-api-alter-add-aggregation-fields')),
        '#required' => TRUE,
      ));

      $form['fields'][$field_id]['actions'] = array(
        '#type' => 'actions',
        'remove' => array(
          '#type' => 'submit',
          '#value' => t('Remove field'),
          '#submit' => array(array($this, 'submitAjaxFieldButton')),
          '#limit_validation_errors' => array(),
          '#name' => 'remove_aggregation_field_' . $field_id,
          '#ajax' => array(
            'callback' => array($this, 'buildAjaxAddFieldButton'),
            'wrapper' => 'search-api-alter-add-aggregation-field-settings',
          ),
        ),
      );
    }
  }

  /**
   * Button submit handler for tracker configure button 'tracker_configure' button.
   */
  public static function submitAjaxFieldButton(array $form, array &$form_state) {
    $form_state['rebuild'] = TRUE;
  }

  /**
   * Button submit handler for tracker configure button 'tracker_configure' button.
   */
  public static function buildAjaxAddFieldButton($form, &$form_state) {
    return $form['processors']['settings']['aggregated_field']['fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    unset($form_state['values']['actions']);
    if (empty($form_state['values']['aggregated_fields'])) {
      return;
    }
    foreach ($form_state['values']['aggregated_fields'] as $field_id => $field) {
      $fields = $form_state['values']['aggregated_fields'][$field_id]['aggregated_fields'] = array_values(array_filter($field['fields']));
      unset($form_state['values']['aggregated_fields'][$field_id]['actions']);
      if ($field['label'] && !$fields) {
        $error_message = t('You have to select at least one field to aggregate. If you want to remove an aggregated field, please delete its name.');
        \Drupal::formBuilder()->setError($form['aggregated_fields'][$field_id]['fields'], $form_state, $error_message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    if (!$items) {
      return;
    }
    if (isset($this->configuration['fields'])) {
      $types = $this->getTypes('type');

      /** @var \Drupal\search_api\Item\ItemInterface[] $items */
      foreach ($items as $item) {
        foreach ($this->configuration['fields'] as $aggregated_field_id => $aggregated_field) {
          if ($aggregated_field['label']) {
            // Extract the selected fields to aggregate from the settings
            $required_fields = array();
            foreach ($aggregated_field['fields'] as $field_id_to_aggregate) {
              // Only include valid and selected fields to aggregate
              if (!isset($required_fields[$field_id_to_aggregate]) && !empty($field_id_to_aggregate)) {
                // Make sure we only get fields from our current datasource type of the given item.
                list($datasource_id) = Utility::splitCombinedId($field_id_to_aggregate);
                if ($datasource_id == $item->getDatasourceId()) {
                  $required_fields[$field_id_to_aggregate]['type'] = $types[$aggregated_field['type']];
                }
              }
            }

            // Get all the available fields
            $given_fields = array();
            foreach ($required_fields as $required_field_id => $required_field_type) {
              $field = $item->getField($required_field_id);
              if ($field instanceof \Drupal\search_api\Item\Field && $field->getValues()) {
                $given_fields[$required_field_id] = $field;
                unset($required_fields[$required_field_id]);
              }
            }

            $missing_fields = array();
            // Get all the missing fields
            foreach ($required_fields as $required_field_id => $required_field_type) {
              $field = Utility::createField($this->index, $required_field_id);
              $missing_fields[$field->getPropertyPath()] = $field;
            }
            // Get the value from the original objects in to the fields
            Utility::extractFields($item->getOriginalObject(), $missing_fields);

            $fields = array_merge($given_fields, $missing_fields);

            $values = array();
            /** @var \Drupal\search_api\Item\FieldInterface[] $fields */
            foreach ($fields as $field) {
              $values[] = $field->getValues();
            }

            // Flatten the values array
            $values = $this->flattenArray($values);

            // Get our reductionType
            $this->reductionType = $aggregated_field['type'];

            // If we want a union, it means a multivalued so we do not need to do a reduce
            if ($this->reductionType != 'union') {
              $values = array(array_reduce($values, array($this, 'reduce'), NULL));
            }

            // Set a failed count to zero
            if ($this->reductionType == 'count' && empty($values)) {
              $values = array(0);
            }

            if($item->getField($aggregated_field_id) instanceof \Drupal\search_api\Item\FieldInterface ) {
              $item->getField($aggregated_field_id)->setValues($values);
            }
          }
        }
      }
    }
  }

  /**
   * Helper method for reducing an array to a single value.
   *
   * @param $a
   * @param $b
   * @return mixed|string
   */
  public function reduce($a, $b) {
    switch ($this->reductionType) {
      case 'concat':
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
   * Helper method for flattening a multi-dimensional array.
   * @param array $data
   * @return array
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
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if ($datasource) {
      return;
    }
    $types = $this->getTypes('type');
    if (isset($this->configuration['fields'])) {
      foreach ($this->configuration['fields'] as $field_id => $field) {
        $definition = array(
          'label' => $field['label'],
          'description' => empty($field['description']) ? '' : $field['description'],
          'type' => $types[$field['type']],
        );
        $properties[$field_id] = new DataDefinition($definition);
      }
    }
  }

  /**
   * Helper method for creating a field description.
   */
  protected function fieldDescription(array $field, array $index_fields) {
    $fields = array();
    foreach ($field['fields'] as $f) {
      $fields[] = isset($index_fields[$f]) ? $index_fields[$f]['label'] : $f;
    }
    $type = $this->getTypes();
    $type = $type[$field['type']];
    return t('A @type aggregation of the following fields: @fields.', array('@type' => $type, '@fields' => implode(', ', $fields)));
  }

  /**
   * Helper method for getting the aggregated types description.
   *
   * @return array
   *   An array of the aggregated types with the description.
   */
  protected function getTypeDescriptions() {
    $types = $this->getTypes();
    $previous_type_descriptions = $this->getTypes('description');

    $type_descriptions = array();
    foreach ($types as $type => $name) {
      $type_descriptions[$type] = array(
        '#type' => 'item',
        '#description' => $previous_type_descriptions[$type],
      );
    }
    return $type_descriptions;
  }

  /**
   * Helper method for getting information about available aggregation types.
   *
   * @param string $info
   *   (optional) One of "name", "type" or "description", to indicate what
   *   values should be returned for the types. Defaults to "name".
   *
   * @return array
   *   An array of the identifiers of the available types mapped to, depending
   *   on $info, their names, their data types or their descriptions.
   */
  protected function getTypes($info = 'name') {
    switch ($info) {
      case 'name':
        return array(
          'union' => t('Union'),
          'concat' => t('Concatenation'),
          'sum' => t('Sum'),
          'count' => t('Count'),
          'max' => t('Maximum'),
          'min' => t('Minimum'),
          'first' => t('First'),
        );
      case 'type':
        return array(
          'union' => 'string',
          'concat' => 'string',
          'sum' => 'integer',
          'count' => 'integer',
          'max' => 'integer',
          'min' => 'integer',
          'first' => 'string',
        );
      case 'description':
        return array(
          'union' => t('The Union aggregation does an union operation of all the values of the field. 2 items with 2 fields become 1 item with 4 fields.'),
          'concat' => t('The Concatenation aggregation concatenates the text data of all contained fields.'),
          'sum' => t('The Sum aggregation adds the values of all contained fields numerically.'),
          'count' => t('The Count aggregation takes the total number of contained field values as the aggregated field value.'),
          'max' => t('The Maximum aggregation computes the numerically largest contained field value.'),
          'min' => t('The Minimum aggregation computes the numerically smallest contained field value.'),
          'first' => t('The First aggregation will simply keep the first encountered field value. This is helpful foremost when you know that a list field will only have a single value.'),
        );
    }
    return array();
  }


  /**
   * Helper method to get the Aggregated Fields that should be listed.
   *
   * @param array $form
   * @param array $form_state
   *
   * @return array
   */
  protected function getAggregatedFields(array &$form, array &$form_state) {
    $additional_fields = empty($this->configuration['fields']) ? array() : $this->configuration['fields'];
    if (!empty($form_state['fields']) && is_array($form_state['fields'])) {
      $additional_fields = array_merge($form_state['fields'], $additional_fields);
    }
    return $additional_fields;
  }

}