<?php
// $Id$

/**
 * Interface defining the methods search backends have to implement.
 */
interface SearchApiBackend {

  /**
   * Initializes a backend object.
   *
   * @param $user_options
   *   An array of options set by the user through the configuration form.
   * @param $hook_options
   *   An array of options defined by the hook advertising this backend.
   */
  public function init(array $user_options, array $hook_options);

  /**
   * Returns a render array for setting this backend's user options.
   */
  public function configuration_form();

}

/**
 * Abstract class with generic implementation of most backend methods.
 */
abstract class SearchApiAbstractBackend implements SearchApiBackend {

  /** Array holding the user-specified options. */
  private $user_options = array();

  /** Array holding the hook-specified options. */
  private $hook_options = array();

  /**
   * Initializes a backend object.
   *
   * @param $user_options
   *   An array of options set by the user through the configuration form.
   * @param $hook_options
   *   An array of options defined by the hook advertising this backend.
   */
  public function init(array $user_options, array $hook_options) {
    $this->user_options = $user_options + $this->default_user_options();
    $this->hook_options = $hook_options + $this->default_hook_options();
  }

  /**
   * @return
   *   An array of default values for this backend's user-specified options.
   */
  protected function default_user_options() {
    return array();
  }

  /**
   * @return
   *   An array of default values for this backend's hook-specified options.
   */
  protected function default_hook_options() {
    return array();
  }

}
