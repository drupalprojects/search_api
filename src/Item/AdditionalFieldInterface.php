<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\AdditionalFieldInterface.
 */

namespace Drupal\search_api\Item;

/**
 * Represents a complex field whose properties can be added to the index.
 */
interface AdditionalFieldInterface extends GenericFieldInterface {

  /**
   * Determines whether this additional field is enabled on the index or not.
   *
   * @return bool
   *   TRUE if the additional field is enabled for the index, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets whether this additional field is enabled on the index or not.
   *
   * @param bool $enabled
   *   The new enabled state of this additional field.
   * @param bool $notify
   *   (optional) Whether to notify the index of the change, i.e., set this
   *   additional field to enabled in its options, too.
   *
   * @return $this
   */
  public function setEnabled($enabled, $notify = FALSE);

}
