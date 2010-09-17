<?php
// $Id$

/**
 * Interface defining the methods search services have to implement.
 *
 * Before a service object is used, the corresponding server's data will be read
 * from the database (see SearchApiAbstractService for a list of fields).
 */
interface SearchApiServiceInterface {

  /**
   * Form callback. Might be called on an uninitialized object - in this case,
   * the form is for configuring a newly created server.
   *
   * @return array
   *   A form array for setting service-specific options.
   */
  public function configurationForm();

  /**
   * Called once, when the server is first created. Allows it to set up its
   * necessary infrastructure.
   */
  public function postCreate();

  /**
   * Notifies this server that its fields are about to be updated. Fields set on
   * this object will still show the old values to allow comparison.
   *
   * @param array $fields
   *   The fields that are about to change, mapped to their values.
   *
   * @return
   *   TRUE, if the update requires reindexing of all content on the server.
   */
  public function postUpdate(array $fields);

  /**
   * Notifies this server that it has been re-enabled and allows the object to
   * react appropriately.
   */
  public function postEnable();

  /**
   * Notifies this server that it has been disabled and allows the object to
   * react appropriately.
   */
  public function postDisable();

  /**
   * Notifies this server that it is about to be deleted from the database and
   * should therefore clean up, if appropriate.
   */
  public function preDelete();

  /**
   * Add a new index to this server.
   *
   * If the index was already added to the server, the object should treat this
   * as if removeIndex() and then addIndex() were called.
   *
   * @param SearchApiIndex $index
   *   The index to add.
   */
  public function addIndex(SearchApiIndex $index);

  /**
   * Notify the server that the indexed field settings for the index have
   * changed.
   * If any user action is necessary as a result of this, the method should
   * use drupal_set_message() to notify the user.
   *
   * @param SearchApiIndex $index
   *   The updated index.
   *
   * @return
   *   TRUE, if this change affected the server in any way that forces it to
   *   re-index the content. FALSE otherwise.
   */
  public function fieldsUpdated(SearchApiIndex $index);

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
   * @param SearchApiIndex $index
   *   The search index for which items should be indexed.
   * @param array $items
   *   An array of items to be indexed, keyed by their id. The values are
   *   associative arrays of the fields to be stored, where each field is an
   *   array with the following keys:
   *   - type: One of the data types recognized by the Search API, or the
   *     special type "tokens" for fulltext fields.
   *   - original_type: The original type of the property as defined through a
   *     hook_entity_property_info().
   *   - value: The value to index.
   *
   *   The special field "search-api-language" contains the item's language and
   *   should always be indexed.
   *
   *   The value of fields with the "tokens" type is an array of tokens. Each
   *   token is an array containing the following keys:
   *   - value: The word that the token represents.
   *   - score: A score for the importance of that word.
   *
   * @throws SearchApiException
   *   If indexing was prevented by a fundamental configuration error.
   *
   * @return array
   *   An array of the ids of all items that were successfully indexed.
   */
  public function indexItems(SearchApiIndex $index, array $items);

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
   * @param SearchApiIndex $index
   *   The index from which items should be deleted, or NULL if all indexes on
   *   this server should be cleared (then, $ids has to be 'all').
   */
  public function deleteItems($ids, SearchApiIndex $index = NULL);

  /**
   * Create a query object for searching on an index lying on this server.
   *
   * @param SearchApiIndex $index
   *   The index to search on.
   * @param $options
   *   Associative array of options configuring this query. See
   *   SearchApiQueryInterface::__construct().
   *
   * @throws SearchApiException
   *   If the server is currently disabled.
   *
   * @return SearchApiQueryInterface
   *   An object for searching the given index.
   */
  public function query(SearchApiIndex $index, $options = array());

  /**
   * Executes a search on the server represented by this object.
   *
   * @param $query
   *   The SearchApiQueryInterface object to execute.
   *
   * @return array
   *   An associative array containing the search results, as required by
   *   SearchApiQueryInterface::execute().
   */
  public function search(SearchApiQueryInterface $query);

}

/**
 * Abstract class with generic implementation of most service methods.
 */
abstract class SearchApiAbstractService implements SearchApiServiceInterface {

  /* Database values that will be set when object is loaded: */

  /**
   * @var integer
   */
  public $id = 0;

  /**
   * @var string
   */
  public $name = '';

  /**
   * @var string
   */
  public $description = '';

  /**
   * @var string
   */
  public $class = '';

  /**
   * @var array
   */
  public $options = array();

  /**
   * @var integer
   */
  public $enabled = 1;

  /**
   * Called once, when the server is first created. Allows it to set up its
   * necessary infrastructure.
   *
   * Does nothing, by default.
   */
  public function postCreate() {
    return;
  }

  /**
   * Notifies this server that its fields are about to be updated. Fields set on
   * this object will still show the old values to allow comparison.
   *
   * Does nothing, by default.
   *
   * @param array $fields
   *   The fields that are about to change, mapped to their values.
   *
   * @return
   *   TRUE, if the update requires reindexing of all content on the server.
   */
  public function postUpdate(array $fields) {
    return FALSE;
  }

  /**
   * Notifies this server that it has been re-enabled and allows the object to
   * react appropriately.
   *
   * Does nothing, by default.
   */
  public function postEnable() {
    return;
  }

  /**
   * Notifies this server that it has been disabled and allows the object to
   * react appropriately.
   *
   * Does nothing, by default.
   */
  public function postDisable() {
    return;
  }

  /**
   * Notifies this server that it is about to be deleted from the database and
   * should therefore clean up, if appropriate.
   *
   *
   * By default, deletes all indexes from this server.
   */
  public function preDelete() {
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
   * @param SearchApiIndex $index
   *   The index to add.
   */
  public function addIndex(SearchApiIndex $index) {
    return;
  }

  /**
   * Notify the server that the indexed field settings for the index have
   * changed.
   * If any user action is necessary as a result of this, the method should
   * use drupal_set_message() to notify the user.
   *
   * @param SearchApiIndex $index
   *   The updated index.
   *
   * @return
   *   TRUE, if this change affected the server in any way that forces it to
   *   re-index the content. FALSE otherwise.
   */
  public function fieldsUpdated(SearchApiIndex $index) {
    return FALSE;
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

  /**
   * Create a query object for searching on an index lying on this server.
   *
   * @param SearchApiIndex $index
   *   The index to search on.
   * @param $options
   *   Associative array of options configuring this query. See
   *   SearchApiQueryInterface::__construct().
   *
   * @throws SearchApiException
   *   If the server is currently disabled.
   *
   * @return SearchApiQueryInterface
   *   An object for searching the given index.
   */
  public function query(SearchApiIndex $index, $options = array()) {
    return new SearchApiQuery($index, $options);
  }

}
