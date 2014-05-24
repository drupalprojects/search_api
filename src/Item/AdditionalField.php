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
    return $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled, $notify = FALSE) {
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

}
