<?php
// $Id$

/**
 * Class representing a search server.
 */
class SearchApiServer extends EntityDBExtendable implements SearchApiServiceInterface {

  /**
   * @var SearchApiServiceInterface
   */
  protected $proxy;
  protected $values = array();

  /**
   * Will be set by the Entity CRUD API for exporting.
   */
  public $module;
  /**
   * Will be set by the Entity CRUD API for exporting.
   */
  public $status;

  /**
   * Constructor as a helper to the parent constructor.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'search_api_server');
  }

  /**
   * Helper method for ensuring the proxy object is set up.
   */
  protected function ensureProxy() {
    if (!isset($this->proxy)) {
      $class = search_api_get_service_info($this->class);
      if ($class && class_exists($class['class'])) {
        $this->proxy = new $class['class']();
      }
      if (!($this->proxy instanceof SearchApiServiceInterface)) {
        throw new SearchApiException(t('Search server with id !id specifies illegal service class !class.', array('!id' => $this->id, '!class' => $this->class)));
      }
      foreach ($this->values as $key => $value) {
        $this->proxy->$key = $value;
      }
    }
  }

  // Magic methods for property magic

  public function __get($name) {
    if (isset($this->proxy)) {
      return $this->proxy->$name;
    }
    return $this->values[$name];
  }

  public function __set($name, $value) {
    if (isset($this->proxy)) {
      $this->proxy->$name = $value;
    }
    $this->values[$name] = $value;
  }

  public function __isset($name) {
    if (isset($this->proxy)) {
      return isset($this->proxy->$name);
    }
    return isset($this->values[$name]);
  }

  public function __unset($name) {
    if (isset($this->proxy)) {
      unset($this->proxy->$name);
    }
    unset($this->values[$name]);
  }

  // If the service class defines additional methods, not specified in the
  // SearchApiServiceInterface interface, then they are called via this magic
  // method.

  public function __call($name, array $arguments) {
    $this->ensureProxy();
    return call_user_func_array(array($this->proxy, $name), $arguments);
  }

  // Proxy methods

  // Since we are implementing the interface, we can't just use the __call() magic method.

  public function configurationForm() {
    $this->ensureProxy();
    return $this->proxy->configurationForm();
  }

  public function configurationFormValidate(array $form, array &$values, $prefix = '') {
    $this->ensureProxy();
    return $this->proxy->configurationFormValidate($form, $values, $prefix);
  }

  public function configurationFormSubmit(array $form, array &$values) {
    $this->ensureProxy();
    return $this->proxy->configurationFormSubmit($form, $values);
  }

  public function supportsFeature($feature) {
    $this->ensureProxy();
    return $this->proxy->supportsFeature($feature);
  }

  public function view() {
    $this->ensureProxy();
    return $this->proxy->view();
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

  public function deleteItems($ids = 'all', SearchApiIndex $index = NULL) {
    $this->ensureProxy();
    return $this->proxy->deleteItems($ids, $index);
  }

  public function query(SearchApiIndex $index, $options = array()) {
    $this->ensureProxy();
    return $this->proxy->query($index, $options);
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

  /**
   * @var SearchApiServer
   */
  protected $server_object = NULL;

  /**
   * @var array
   */
  protected $processors = array();

  /**
   * @var array
   */
  protected $added_properties = array();

  /**
   * @var array
   */
  protected $fulltext_fields = NULL;

  // Database values that will be set when object is loaded

  /**
   * @var integer
   */
  public $id;

  /**
   * @var string
   */
  public $name;

  /**
   * @var string
   */
  public $machine_name;

  /**
   * @var string
   */
  public $description;

  /**
   * @var integer
   */
  public $server;

  /**
   * @var string
   */
  public $entity_type;

  /**
   * @var array
   */
  public $options;

  /**
   * @var integer
   */
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
   *   Associative array of options configuring this query. See
   *   SearchApiQueryInterface::__construct().
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
   * Indexes items on this index. Will return an array of IDs of items that
   * should be marked as indexed – i.e. items that were either rejected by a
   * data-alter callback or were successfully indexed.
   *
   * @param array $items
   *   An array of entities to index.
   *
   * @return array
   *   An array of the IDs of all items that should be marked as indexed.
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

    // Marks all items that are rejected by a data-alter callback as indexed.
    $ret = array_keys($items);
    $this->dataAlter($items);
    $ret = array_diff($ret, array_keys($items));
    if (!$items) {
      watchdog('search_api', t('Indexing the items of type "!type" with the following IDs was rejected by data-alter callbacks: !list.', array('type' => $this->entity_type, '!list' => implode(', ', $ret))));
      return $ret;
    }
    $wrappers = array();
    foreach ($items as $id => $item) {
      $wrappers[$id] = entity_metadata_wrapper($this->entity_type, $item, array('property info alter' => array($this, 'propertyInfoAlter')));
    }

    $items = array();
    foreach ($wrappers as $id => $wrapper) {
      $items[$id] = search_api_extract_fields($wrapper, $fields);
    }

    $this->preprocessIndexItems($items);

    return array_merge($ret, $this->server()->indexItems($this, $items));
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
      'search_api_language' => array(
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
   * @param array $response
   *   An array containing the search results. See
   *   SearchApiServiceInterface->search() for the detailled format.
   * @param SearchApiQuery $query
   *   The object representing the executed query.
   *
   * @return SearchApiIndex
   *   The called object.
   */
  public function postprocessSearchResults(array &$response, SearchApiQuery $query) {
    $this->prepareProcessors();
    // Postprocessing is done in exactly the opposite direction than preprocessing.
    foreach (array_reverse($this->processors) as $processor) {
      $processor->postprocessSearchResults($response, $query);
    }
    return $this;
  }

  /**
   * Convenience method for getting all of this index' fulltext fields.
   *
   * @param boolean $only_indexed
   *   If set to TRUE, only the indexed fulltext fields will be returned.
   *
   * @return array
   *   An array containing all (or all indexed) fulltext fields defined for this
   *   index.
   */
  public function getFulltextFields($only_indexed = TRUE) {
    if ($this->fulltext_fields === NULL) {
      $this->fulltext_fields = array();
      if (empty($this->options['fields'])) {
        return array();
      }
      foreach ($this->options['fields'] as $key => $field) {
        if (search_api_is_text_type($field['type']) && (!$only_indexed || $field['indexed'])) {
          $this->fulltext_fields[] = $key;
        }
      }
    }
    return $this->fulltext_fields;
  }

}
