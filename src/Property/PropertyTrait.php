<?php
/**
 * @file
 * Contains \Drupal\search_api\Property\PropertyTrait.
 */

namespace Drupal\search_api\Property;

/**
 * Contains methods for implementing a simple property object.
 *
 * @see \Drupal\search_api\Property\PropertyInterface
 */
trait PropertyTrait {

  /**
   * The locked state of the property.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * The hidden state of the property.
   *
   * @var bool
   */
  protected $hidden = FALSE;

  /**
   * The fixed field settings of the property.
   *
   * @var array
   */
  protected $fieldSettings = array();

  /**
   * Sets the locked state.
   *
   * @param bool $locked
   *   (optional) The new locked state.
   *
   * @return $this
   */
  public function setLocked($locked = TRUE) {
    $this->locked = $locked;
    return $this;
  }

  /**
   * Determines whether this processor should always be enabled.
   *
   * @return bool
   *   TRUE if this processor should be forced enabled; FALSE otherwise.
   *
   * @see \Drupal\search_api\Property\PropertyInterface::isLocked()
   */
  public function isLocked() {
    return $this->locked;
  }

  /**
   * Sets the hidden state.
   *
   * @param bool $hidden
   *   (optional) The new hidden state.
   *
   * @return $this
   */
  public function setHidden($hidden = TRUE) {
    $this->hidden = $hidden;
    return $this;
  }

  /**
   * Determines whether this processor should be hidden from the user.
   *
   * @return bool
   *   TRUE if this processor should be hidden from the user; FALSE otherwise.
   *
   * @see \Drupal\search_api\Property\PropertyInterface::isHidden()
   */
  public function isHidden() {
    return $this->hidden;
  }

  /**
   * Sets the field settings.
   *
   * @param mixed $fieldSettings
   *   The new field settings.
   *
   * @return $this
   */
  public function setFieldSettings($fieldSettings) {
    $this->fieldSettings = $fieldSettings;
    return $this;
  }

  /**
   * Retrieves the settings that the field should have, if it is locked.
   *
   * @return array
   *   An array of field settings, or an empty array to use the defaults.
   *
   * @see \Drupal\search_api\Property\PropertyInterface::getFieldSettings()
   */
  public function getFieldSettings() {
    return $this->fieldSettings;
  }

}
