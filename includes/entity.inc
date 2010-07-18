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

  /**
   * Constructor as a helper to the parent constructor.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'search_api_server');
  }

  public function ensureProxy() {
    if (!isset($this->proxy)) {
      $class = search_api_get_service_info($this->class);
      if ($class && class_exists($class['class'])) {
        $this->proxy = new $class['class']();
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
    }
  }

  // Proxy methods

  // Since we are implementing the interface, we can't just use the __call() magic method.
  // @todo Is having an interface (and defined methods) a bigger advantage than clarity and shortness of code?

  public function configurationForm() {
    $this->ensureProxy();
    return $this->proxy->configurationForm();
  }

  public function postCreate() {
    $this->ensureProxy();
    return $this->proxy->postCreate();
  }

  public function postUpdate(array $fields) {
    $this->ensureProxy();
    return $this->proxy->postUpdate($fields);
  }

  public function postEnable() {
    $this->ensureProxy();
    return $this->proxy->postEnable();
  }

  public function postDisable() {
    $this->ensureProxy();
    return $this->proxy->postDisable();
  }

  public function preDelete() {
    $this->ensureProxy();
    return $this->proxy->preDelete();
  }

  public function addIndex(SearchApiIndex $index) {
    $this->ensureProxy();
    return $this->proxy->addIndex($index);
  }

  public function fieldsUpdated(SearchApiIndex $index) {
    $this->ensureProxy();
    return $this->proxy->fieldsUpdated($index);
  }

  public function removeIndex($index) {
    $this->ensureProxy();
    return $this->proxy->removeIndex($index);
  }

  public function indexItems(SearchApiIndex $index, array $items) {
    $this->ensureProxy();
    return $this->proxy->indexItems($index, $items);
  }

  public function deleteItems($ids, SearchApiIndex $index = NULL) {
    $this->ensureProxy();
    return $this->proxy->deleteItems($ids, $index);
  }

  public function query(SearchApiIndex $index, $options = array()) {
    $this->ensureProxy();
    return $this->proxy->query($index);
  }

  public function search(SearchApiQueryInterface $query) {
    $this->ensureProxy();
    return $this->proxy->search($query);
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
   * Constructor as a helper to the parent constructor.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'search_api_index');
  }

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
