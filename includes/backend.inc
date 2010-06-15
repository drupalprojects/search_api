<?php
// $Id$

/**
 * Interface defining the methods search backends have to implement.
 */
interface SearchApiBackend {

  /**
   * Initializes a backend object.
   *
   * @param $options
   *   An array of options set by the user through the configuration form.
   */
  public function init(array $options);

  /**
   * Returns a render array for setting backend-specific options.
   */
  public function configuration_form();

  /**
   * Index the specified items.
   *
   * @param $index The search index for which items should be indexed.
   * @param $items An array of objects containing the item data to be indexed.
   *
   * @return An array of the ids of all items that were successfully indexed.
   */
  function index($index, $items);

}

/**
 * Abstract class with generic implementation of most backend methods.
 */
abstract class SearchApiAbstractBackend implements SearchApiBackend {

  /** Array holding the user-specified options. */
  private $options = array();

  /**
   * Initializes a backend object.
   *
   * @param $options
   *   An array of options set by the user through the configuration form.
   */
  public function init(array $options) {
    $this->options = $options + $this->default_values();
  }

  /**
   * @return
   *   An array of default values for this backend's options.
   */
  protected function default_values() {
    return array();
  }

}
