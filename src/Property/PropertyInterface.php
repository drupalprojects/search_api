<?php

/**
 * @file
 * Contains Drupal\search_api\Property\SearchPropertyInterface.
 */

namespace Drupal\search_api\Property;

use Drupal\Core\TypedData\DataDefinitionInterface;

/**
 * Represents a special kind of data definition used in the Search API.
 */
interface PropertyInterface extends DataDefinitionInterface {

  /**
   * Determines whether this processor should always be enabled.
   *
   * @return bool
   *   TRUE if this processor should be forced enabled; FALSE otherwise.
   */
  public function isLocked();

  /**
   * Determines whether this processor should be hidden from the user.
   *
   * @return bool
   *   TRUE if this processor should be hidden from the user; FALSE otherwise.
   */
  public function isHidden();

  /**
   * Retrieves the settings that the field should have, if it is locked.
   *
   * @return array
   *   An array of field settings, or an empty array to use the defaults.
   */
  public function getFieldSettings();

  /**
   * Returns the wrapped property, if any.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The wrapper data definition, or $this if this property wasn't created as
   *   a wrapper to an existing data definition.
   */
  public function getWrappedProperty();

}
