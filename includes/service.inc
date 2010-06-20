<?php
// $Id$

/**
 * Interface defining the methods search services have to implement.
 *
 * Before a service object is used, the corresponding server's data will be read
 * from the database (see search_api_schema() for a list of fields) and the
 * init() method will be called with the values set by the user and the defining
 * hook.
 */
interface SearchApiServiceInterface {

  /**
   * Initializes a service object.
   *
   * @param $options
   *   An array of options set by the user through the configuration form.
   * @param $init_args
   *   An array of init arguments specified by the defining hook.
   */
  public function init(array $options, array $init_args);

  /**
   * Returns a render array for setting service-specific options.
   */
  public function configuration_form();

  /**
   * Index the specified items.
   *
   * @param $index
   *   The search index for which items should be indexed.
   * @param $items
   *   An array of objects containing the item data to be indexed.
   *
   * @return
   *   An array of the ids of all items that were successfully indexed.
   */
  public function index($index, $items);

  /**
   * Executes a search on the server represented by this object.
   *
   * @param $index
   *   The index to search items on.
   * @param $query
   *   The SearchApiQuery to execute.
   * @param $options
   *   An associative array of search options. The following are recognized:
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *
   * @return
   *   An associative array containing the search results.
   *   @todo Specify this in a more detailled way.
   */
  public function search($index, $query, array $options = array());

}

/**
 * Abstract class with generic implementation of most service methods.
 */
abstract class SearchApiAbstractService implements SearchApiServiceInterface {

  /** Array holding the user-specified options. */
  private $user_options = array();

  /** Array holding the hook-specified init args. */
  private $init_args = array();

  /** Database values that will be set when object is loaded: */
  public $id = 0;
  public $name = '';
  public $description = '';
  public $class = '';
  public $options = array();
  public $enabled = 1;

  /**
   * Initializes a service object.
   *
   * @param $user_options
   *   An array of options set by the user through the configuration form.
   * @param $init_args
   *   An array of init arguments specified by the defining hook.
   */
  public function init(array $user_options, array $init_args) {
    $this->user_options = $user_options + $this->default_user_option_values();
    $this->init_args = $init_args + $this->default_init_args();
  }

  /**
   * @return
   *   An array of default values for this service's user-defined options.
   */
  protected function default_user_option_values() {
    return array();
  }

  /**
   * @return
   *   An array of default values for this service's init args.
   */
  protected function default_init_args() {
    return array();
  }

}
