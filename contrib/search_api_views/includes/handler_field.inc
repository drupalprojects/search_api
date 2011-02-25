<?php

/**
 * Views field handler base class for this module, handling all fields that
 * don't need any special treatment and don't have special options.
 *
 * Handles lists automatically.
 */
class SearchApiViewsHandlerField extends views_handler_field {

  /**
   * Array of fields that should additionally be added to the query.
   *
   * @var array
   */
  protected $additional_fields = array();

  /**
   * The associated views query object.
   *
   * @var SearchApiViewsQuery
   */
  public $query;

  /**
   * Whether this field displays a list type or not.
   *
   * @var boolean
   */
  protected $is_list_type = FALSE;

  /**
   * Initialize this field handler.
   */
  public function init($view, array &$options) {
    parent::init($view, $options);

    $this->is_list_type = empty($this->definition['type']) ? FALSE : search_api_is_list_type($this->definition['type']);
  }

  /**
   * Specifies the options this handler uses.
   */
  public function option_definition() {
    $options = parent::option_definition();
    $options['link_to_entity'] = array('default' => FALSE);
    $options['list']['mode'] = array('default' => 'collapse');
    $options['list']['glue'] = array('default' => ', ');
    return $options;
  }

  /**
   * Provide "Link to entity" option, and list handling options if applicable.
   */
  public function options_form(array &$form, array &$form_state) {
    parent::options_form($form, $form_state);
    if ($this->is_list_type) {
      $form['list']['mode'] = array(
        '#type' => 'select',
        '#title' => t('List handling'),
        '#options' => array(
          'collapse' => t('Concatenate values using the list seperator'),
          'first' => t('Show first (if present)'),
          'count' => t('Show item count'),
        ),
        '#default_value' => $this->options['list']['mode'],
      );
      $form['list']['glue'] = array(
        '#type' => 'textfield',
        '#title' => t('List seperator'),
        '#default_value' => $this->options['list']['glue'],
      );
    }
    $form['link_to_entity'] = array(
      '#type' => 'checkbox',
      '#title' => t('Link this field to its entity'),
      '#description' => t('This will override any other link you have set.'),
      '#default_value' => $this->options['link_to_entity'],
    );
  }

  /**
   * Called to add the field to a query.
   */
  public function query() {
    // Add the field.
    $this->query->addField($this->real_field);
    $this->addAdditionalFields();
  }

  /**
   * Add 'additional' fields to the query.
   *
   * @param $fields
   *   An array of fields. The key is an identifier used to later find the
   *   field alias used. The value is either a string in which case it's
   *   assumed to be a field on this handler's table; or it's an array in the
   *   form of
   *   @code array('table' => $tablename, 'field' => $fieldname) @endcode
   */
  public function addAdditionalFields($fields = NULL) {
    if (!isset($fields)) {
      // notice check
      if (empty($this->additional_fields)) {
        return;
      }
      $fields = $this->additional_fields;
    }
    if (!empty($fields) && is_array($fields)) {
      foreach ($fields as $identifier => $info) {
        $this->query->addField(is_array($info) ? $info['field'] : $info);
      }
    }
  }

  /**
   * Called to determine what to tell the clicksorter.
   */
  public function click_sort($order) {
    $this->query->sort($this->real_field, $order);
  }

  /**
   * Render the field.
   *
   * @param array $values
   *   The values retrieved from the database.
   */
  public function render(array $values) {
    $value = $values[$this->real_field];
    if (is_array($value)) {
      $value = $this->renderArray($value);
    }
    else {
      $value = $this->renderValue($value);
    }
    return $this->renderLink($value, $values);
  }

  /**
   * Render a list of values.
   */
  protected function renderArray($value) {
    if (isset($this->options['list']['mode'])) {
      if ($this->options['list']['mode'] == 'first') {
        $value = count($value) ? array_shift($value) : NULL;
        if (is_array($value)) {
          return $this->renderArray($value);
        }
        elseif (isset($value)) {
          return $this->renderValue($value);
        }
        return NULL;
      }
      if ($this->options['list']['mode'] == 'count') {
        return count($value);
      }
    }
    $vs = array();
    foreach ($value as $v) {
      $vs[] = is_array($v) ? $this->renderArray($v) : $this->renderValue($v);
    }
    $glue = isset($this->options['list']['glue']) ? $this->options['list']['glue'] : ', ';
    return implode($glue, $vs);
  }

  /**
   * Render a value as a link to the entity, if applicable.
   */
  // @todo Let e.g. author:name be linked to author instead
  protected function renderLink($value, array $values) {
    if (!$this->options['link_to_entity']) {
      return $value;
    }
    $entity = $values['search_api_views_entity'];
    $type = isset($values['search_api_views_entity_type']) ? $values['search_api_views_entity_type'] : $this->query->getIndex()->entity_type;
    $url = entity_uri($type, $entity);
    return l($value, $url['path'], array('html' => TRUE) + $url['options']);
  }

  /**
   * Helper function for rendering a single value. Might be overridden by child
   * classes to provide more specific rendering.
   */
  protected function renderValue($value) {
    return $value;
  }

}
