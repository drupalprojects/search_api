<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\AdditionalField.
 */

namespace Drupal\search_api\Item;

/**
 * Represents a complex field whose properties can be added to the index.
 */
class AdditionalField implements AdditionalFieldInterface {

  use FieldTrait;

  /**
   * Whether this additional field is enabled on the index or not.
   *
   * @var bool
   */
  protected $enabled;

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    if (!isset($this->enabled)) {
      $additional_fields = $this->index->getOption('additional fields', array());
      $this->enabled = isset($additional_fields[$this->fieldIdentifier]);
    }
    return $this->isLocked() || $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled, $notify = FALSE) {
    if ($this->isLocked()) {
      return $this;
    }
    $this->enabled = $enabled;
    if ($notify) {
      $additional_fields = $this->index->getOption('additional fields', array());
      if ($enabled) {
        $additional_fields[$this->fieldIdentifier] = $this->fieldIdentifier;
      }
      else {
        unset($additional_fields[$this->fieldIdentifier]);
      }
      $this->index->setOption('additional fields', $additional_fields);
    }
    return $this;
  }

  /**
   * Determines whether this additional field's state should be locked.
   *
   * @return bool
   *   TRUE if a child of this additional field is enabled or the field was
   *   nevertheless marked as locked, FALSE otherwise.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::isLocked()
   */
  public function isLocked() {
    if (!isset($this->locked)) {
      $additional_fields = $this->index->getOption('additional fields', array());
      $prefix = $this->getFieldIdentifier() . ':';
      $prefix_len = strlen($prefix);
      $this->locked = FALSE;
      foreach (array_keys($additional_fields) as $field_id) {
        if (substr($field_id, 0, $prefix_len) == $prefix) {
          $this->locked = TRUE;
          break;
        }
      }

    }
    return $this->locked;
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    $out = $this->getLabel() . ' [' . $this->getFieldIdentifier() . ']: ';
    if (!$this->isEnabled()) {
      $out .= 'not ';
    }
    $out .= 'enabled';
    if ($this->isLocked()) {
      $out .= ' (LOCKED)';
    }
    return $out;
  }

}
