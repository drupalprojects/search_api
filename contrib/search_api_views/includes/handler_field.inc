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
   * Constructor; calls to base object constructor.
   */
  function construct() {
    parent::construct();

    $this->format = $this->definition['format'];

    $this->additional_fields = array();
    if (!is_numeric($this->format)) {
      $this->additional_fields['format'] = array('field' => $this->format);
    }
  }

  function render($values) {
    $value = $values->{$this->field_alias};
    $format = is_numeric($this->format) ? $this->format : $values->{$this->aliases['format']};
    if ($value) {
      return check_markup($value, $format, '');
    }
  }

  function element_type() {
    if (isset($this->definition['element type'])) {
      return $this->definition['element type'];
    }

    return 'div';
  }

}
