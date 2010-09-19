<?php
// $Id$

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
  public function init(view $view, array &$options) {
    parent::init($view, $options);

    $this->is_list_type = empty($this->definition['type']) ? FALSE : search_api_is_list_type($this->definition['type']);
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
   * @param $values
   *   The values retrieved from the database.
   */
  public function render($values) {
    $value = $values[$this->real_field];
    // if ($this->is_list_type)?
    if (is_array($value)) {
      $vs = array();
      foreach ($value as $v) {
        $vs[] = $this->renderValue($v);
      }
      // @todo Make this string configurable
      return implode(', ', $vs);
    }
    return $this->renderValue($value);
  }

  /**
   * Helper function for rendering a single value. Might be overridden by child
   * classes to provide more specific rendering.
   */
  protected function renderValue($value) {
    return check_plain($value);
  }

}
