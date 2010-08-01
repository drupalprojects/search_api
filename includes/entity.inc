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

  /**
   * Helper method for ensuring the proxy object is set up.
   */
  private function ensureProxy() {
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
  private $processors = array();
  private $added_properties = array();

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
   * @return SearchApiServer
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
   * @return SearchApiQueryInterface
   *   A query object for searching this index.
   */
  public function query($options = array()) {
    if (!$this->enabled) {
      throw new SearchApiException(t('Cannot search on a disabled index.'));
    }
    return $this->server()->query($this, $options);
  }


  /**
   * Indexes items for the specified index. Only items marked as changed are
   * indexed, in their order of change (if known).
   *
   * @param array $items
   *   An array of entities to index.
   *
   * @return array
   *   An array of the ids of all items that were successfully indexed.
   */
  public function index(array $items) {
    if (!$this->enabled) {
      throw new SearchApiException(t("Couldn't index values on '!name' index (index is disabled)", array('!name' => $this->name)));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on '!name' index (no fields selected)", array('!name' => $this->name)));
    }

    $fields = $this->options['fields'];
    foreach ($fields as $field => $info) {
      if (!$info['indexed']) {
        unset($fields[$field]);
      }
      unset($fields[$field]['indexed']);
    }
    if (empty($fields)) {
      throw new SearchApiException(t("Couldn't index values on '!name' index (no fields selected)", array('!name' => $this->name)));
    }

    $this->dataAlter($items);
    if (!$items) {
      return 0;
    }
    $wrappers = array();
    foreach ($items as $id => $item) {
      $wrappers[$id] = entity_metadata_wrapper($this->entity_type, $item, array('property info alter' => array($this, 'propertyInfoAlter')));
    }

    $items = array();
    foreach ($wrappers as $id => $wrapper) {
      $item = array();
      foreach ($fields as $field => $info) {
        $tmp = $wrapper;
        foreach (explode(':', $field) as $property) {
          $tmp = $tmp->$property;
        }
        $item[$field] = $info;
        $item[$field]['value'] = $tmp->value();
        $property_info = $tmp->info();
        $item[$field]['original_type'] = $property_info['type'];
      }
      $items[$id] = $item;
    }

    $this->preprocessIndexItems($items);

    return $this->server()->indexItems($this, $items);
  }

  /**
   * Calls data alteration hooks for a set of items, according to the index
   * options. Does nothing if hooks have already been called.
   *
   * @param array $items
   *   An array of items to be altered.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  public function dataAlter(array &$items) {
    if (!empty($this->added_properties)) {
      return $this;
    }
    $this->added_properties = array(
      'search-api-language' => array(
        'label' => t('Item language'),
        'description' => t("A field added by the search framework to let components determine an item's language. Is always indexed."),
        'type' => 'string',
      ),
    );
    foreach ($items as &$item) {
      $item->search_api_language = isset($item->language) ? $item->language : LANGUAGE_NONE;
    }

    if (empty($this->options['data_alter_callbacks'])) {
      return;
    }

    foreach ($this->options['data_alter_callbacks'] as $func => $settings) {
      if (empty($settings['status'])) {
        continue;
      }
      if (!is_callable($func)) {
        watchdog('search_api', t('Undefined data alter callback function !function() specified in index !name', array('!function' => $func, '!name' => $this->name)), NULL, WATCHDOG_WARNING);
        continue;
      }
      $ret = $func($this, $items);
      if (is_array($ret)) {
        $this->added_properties += $ret;
      }
    }

    return $this;
  }

  /**
   * Property info alter callback that adds the infos of the properties added by
   * data alter callbacks.
   *
   * @param EntityMetadataWrapper $wrapper
   *   The wrapped data.
   * @param $property_info
   *   The original property info.
   *
   * @return array
   *   The altered property info.
   */
  public function propertyInfoAlter(EntityMetadataWrapper $wrapper, array $property_info) {
    if (!empty($this->added_properties)) {
      $property_info['properties'] += $this->added_properties;
    }
    return $property_info;
  }

  /**
   * Fills the $processors array for use by the pre-/postprocessing functions.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  protected function prepareProcessors() {
    if (empty($this->options['processors']) || !empty($this->processors)) {
      return $this;
    }

    $processor_settings = $this->options['processors'];
    $infos = search_api_get_processors();

    foreach ($processor_settings as $id => $settings) {
      if (empty($settings['status'])) {
        continue;
      }
      if (empty($infos[$id]) || !class_exists($infos[$id]['class'])) {
        watchdog('search_api', t('Undefined processor !class specified in index !name', array('!class' => $id, '!name' => $this->name)), NULL, WATCHDOG_WARNING);
        continue;
      }
      $class = $infos[$id]['class'];
      $processor = new $class($this, isset($settings['settings']) ? $settings['settings'] : array());
      if (!($processor instanceof SearchApiProcessorInterface)) {
        watchdog('search_api', t('Unknown processor class !class specified for processor !name', array('!class' => $class, '!name' => $id)), NULL, WATCHDOG_WARNING);
        continue;
      }

      $this->processors[$id] = $processor;
    }
    return $this;
  }

  /**
   * Preprocess data items for indexing. Data added by data alter callbacks will
   * be available on the items.
   *
   * Typically, a preprocessor will execute its preprocessing (e.g. stemming,
   * n-grams, word splitting, stripping stop words, etc.) only on the items'
   * fulltext fields. Other fields should usually be left untouched.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  public function preprocessIndexItems(array &$items) {
    $this->prepareProcessors();
    foreach ($this->processors as $processor) {
      $processor->preprocessIndexItems($items);
    }
    return $this;
  }


  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param SearchApiQuery $query
   *   The object representing the query to be executed.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  public function preprocessSearchQuery(SearchApiQuery $query) {
    $this->prepareProcessors();
    foreach ($this->processors as $processor) {
      $processor->preprocessSearchQuery($query);
    }
    return $this;
  }

  /**
   * Postprocess search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $results
   *   An array containing the search results. See
   *   SearchApiServiceInterface->search() for the detailled format.
   * @param SearchApiQuery $query
   *   The object representing the executed query.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  public function postprocessSearchResults(array &$results, SearchApiQuery $query) {
    $this->prepareProcessors();
    // Postprocessing is done in exactly the opposite direction than preprocessing.
    foreach (array_reverse($this->processors) as $processor) {
      $processor->preprocessSearchQuery($results, $query);
    }
    return $this;
  }

}
