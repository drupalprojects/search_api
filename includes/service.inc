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
   * @param $init_args
   *   An array of init arguments specified by the defining hook.
   */
  public function init(array $init_args);

  /**
   * Form callback. Might be called on an uninitialized object - in this case,
   * the form is for configuring a newly created server.
   *
   * @return
   *   A form array for setting service-specific options.
   */
  public function configurationForm();

  /**
   * Called once, when the server is first created. Allows it to set up its
   * necessary infrastructure.
   */
  public function create();

  /**
   * Notifies this server that its fields are about to be updated. Fields set on
   * this object will still show the old values to allow comparison.
   *
   * @param array $fields
   *   The fields that are about to change.
   */
  public function update(array $fields);

  /**
   * Notifies this server that it has been re-enabled and allows the object to
   * react appropriately.
   */
  public function enable();

  /**
   * Notifies this server that it has been disabled and allows the object to
   * react appropriately.
   */
  public function disable();

  /**
   * Notifies this server that it is about to be deleted from the database and
   * should therefore clean up, if appropriate.
   */
  public function delete();

  /**
   * Add a new index to this server.
   *
   * If the index was already added to the server, the object should treat this
   * as if removeIndex() and then addIndex() were called.
   *
   * @param stdClass $index
   *   The index to add.
   */
  public function addIndex(stdClass $index);

  /**
   * Notify the server that the indexed field settings for the index have
   * changed.
   * If any user action is necessary as a result of this, the method should
   * use drupal_set_message() to notify the user.
   *
   * @param stdClass $index
   *   The updated index.
   *
   * @return
   *   TRUE, if this change affected the server in any way that forces it to
   *   re-index the content. FALSE otherwise.
   */
  public function fieldsUpdated(stdClass $index);

  /**
   * Remove an index from this server.
   *
   * This might mean that the index has been deleted, or reassigned to a
   * different server. If you need to distinguish between these cases, inspect
   * $index->server.
   *
   * If the index wasn't added to the server, the method call should be ignored.
   *
   * @param $index
   *   Either an object representing the index to remove, or its id (if the
   *   index was completely deleted).
   */
  public function removeIndex($index);

  /**
   * Index the specified items.
   *
   * @param stdClass $index
   *   The search index for which items should be indexed.
   * @param array $items
   *   An array of objects containing the item data to be indexed.
   *
   * @return
   *   An array of the ids of all items that were successfully indexed.
   */
  public function indexItems(stdClass $index, array $items);

  /**
   * Delete items from an index on this server.
   *
   * Might be either used to delete some items (given by their ids) from a
   * specified index, or all items from that index, or all items from all
   * indexes on this server.
   *
   * @param $ids
   *   Either an array containing the ids of the items that should be deleted,
   *   or 'all' if all items should be deleted. Other formats might be
   *   recognized by implementing classes, but these are not standardized.
   * @param stdClass $index
   *   The index from which items should be deleted, or NULL if all indexes on
   *   this server should be cleared (then, $ids has to be 'all').
   */
  public function deleteItems($ids, stdClass $index = NULL);

  /**
   * Executes a search on the server represented by this object.
   *
   * @param $index
   *   The index to search items on.
   * @param $query
   *   The SearchApiQueryInterface object to execute.
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
  public function search(stdClass $index, SearchApiQueryInterface $query, array $options = array());

}

/**
 * Abstract class with generic implementation of most service methods.
 */
abstract class SearchApiAbstractService implements SearchApiServiceInterface {

  /** Array holding the hook-specified init args. */
  protected $init_args = array();

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
  public function init(array $init_args) {
    $this->init_args = $init_args + $this->default_init_args();
  }

  /**
   * @return
   *   An array of default values for this service's init args.
   */
  protected function default_init_args() {
    return array();
  }

  /**
   * Called once, when the server is first created. Allows it to set up its
   * necessary infrastructure.
   *
   * Does nothing, by default.
   */
  public function create() {
    return;
  }

  /**
   * Notifies this server that its fields are about to be updated. Fields set on
   * this object will still show the old values to allow comparison.
   *
   * Does nothing, by default.
   *
   * @param array $fields
   *   The fields that are about to change.
   */
  public function update(array $fields) {
    return;
  }

  /**
   * Notifies this server that it has been re-enabled and allows the object to
   * react appropriately.
   *
   * Does nothing, by default.
   */
  public function enable() {
    return;
  }

  /**
   * Notifies this server that it has been disabled and allows the object to
   * react appropriately.
   *
   * Does nothing, by default.
   */
  public function disable() {
    return;
  }

  /**
   * Notifies this server that it is about to be deleted from the database and
   * should therefore clean up, if appropriate.
   *
   *
   * By default, deletes all indexes from this server.
   */
  public function delete() {
    $indexes = search_api_list_indexes(array('server' => $this->id));
    foreach ($indexes as $index) {
      $this->removeIndex($index);
    }
  }

  /**
   * Add a new index to this server.
   *
   * Does nothing, by default.
   *
   * @param stdClass $index
   *   The index to add.
   */
  public function addIndex(stdClass $index) {
    return;
  }

  /**
   * Remove an index from this server.
   *
   * This might mean that the index has been deleted, or reassigned to a
   * different server. If you need to distinguish between these cases, inspect
   * $index->server.
   *
   * By default, removes all items from that index.
   *
   * @param $index
   *   Either an object representing the index to remove, or its id (if the
   *   index was completely deleted).
   */
  public function removeIndex($index) {
    $this->deleteItems('all', $index);
  }

}
