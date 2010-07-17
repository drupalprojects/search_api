<?php
// $Id$

/**
 * Class representing a search server.
 */
class SearchApiServer extends EntityDBExtendable implements SearchApiServiceInterface {

  private $proxy;

  // Database values that will be set when object is loaded

  public $id;
  public $name;
  public $description;
  public $class;
  public $options = array();
  public $enabled;

  // Proxy methods

  public function init(array $init_args) {
    // When init() is called, we initalize the proxy.
    $class = search_api_get_service_info($this->class);
    if ($class && class_exists($class)) {
      $this->proxy = new $class();
    }
    if (!($this->proxy instanceof SearchApiServiceInterface)) {
      throw new SearchApiException(t('Search server with id !id specifies illegal service class !class.', array('!id' => $this->id, '!class' => $this->class)));
    }
    $this->proxy->id = $this->id;
    $this->proxy->name = $this->name;
    $this->proxy->description = $this->description;
    $this->proxy->class = $this->class;
    $this->proxy->options = $this->options;
    $this->proxy->enabled = $this->enabled;

    return $proxy->init($init_args);
  }

  public function configurationForm() {
    return $proxy->configurationForm();
  }

  public function create() {
    return $proxy->create();
  }

  public function update(array $fields) {
    return $proxy->update($fields);
  }

  public function enable() {
    return $proxy->enable();
  }

  public function disable() {
    return $proxy->disable();
  }

  public function delete() {
    return $proxy->delete();
  }

  public function addIndex(SearchApiIndex $index) {
    return $proxy->addIndex($index);
  }

  public function fieldsUpdated(SearchApiIndex $index) {
    return $proxy->fieldsUpdated($index);
  }

  public function removeIndex($index) {
    return $proxy->removeIndex($index);
  }

  public function indexItems(SearchApiIndex $index, array $items) {
    return $proxy->indexItems($index, $items);
  }

  public function deleteItems($ids, SearchApiIndex $index = NULL) {
    return $proxy->deleteItems($ids, $index);
  }

  public function search(SearchApiIndex $index, SearchApiQueryInterface $query, array $options = array()) {
    return $proxy->search($index, $query, $options);
  }

}

/**
 * Class representing a search index.
 */
class SearchApiIndex extends EntityDBExtendable {

  private $server_object = NULL;

  // Database values that will be set when object is loaded

  public $id;
  public $name;
  public $description;
  public $server;
  public $entity_type;
  public $options;
  public $enabled;

  /**
   * @return
   *   The server associated with this index, or NULL if this index currently
   *   doesn't lie on a server.
   */
  public function server() {
    if (!isset($this->server_object)) {
      $this->server_object = $this->server ? search_api_server_load($this->server) : FALSE;
    }
    return $this->server_object ? $this->server_object : NULL;
  }

  /**
   * Create a query object for this index.
   *
   * @param $options
   *   Associative array of options configuring this query. Recognized options
   *   are:
   *   - conjunction: The type of conjunction to use for this query - either
   *     'AND' or 'OR'. 'AND' by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See SearchApiQuery::parseModes() for
   *     recognized parse modes.
   *   - languages: The languages to search for.
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *
   * @throws SearchApiException
   *   If the index is currently disabled.
   *
   * @return
   *   A SearchApiQueryInterface object for searching this index.
   */
  public function query($options = array()) {
    if (!$this->enabled) {
      throw new SearchApiException(t('Cannot search on a disabled index.'));
    }
    return $this->server()->query($this, $options);
  }

}
