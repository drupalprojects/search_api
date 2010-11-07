<?php
// $Id$

/**
 * Class representing a search server.
 *
 * This can handle the same calls as defined in the SearchApiServiceInterface
 * and pass it on to the service implementation appropriate for this server.
 */
class SearchApiServer extends EntityDBExtendable {

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
  public $machine_name = '';

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
   * Will be set by the Entity CRUD API for exporting.
   */
  public $module;
  /**
   * Will be set by the Entity CRUD API for exporting.
   */
  public $status;

  /**
   * Proxy object for invoking service methods.
   *
   * @var SearchApiServiceInterface
   */
  protected $proxy;

  /**
   * Constructor as a helper to the parent constructor.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'search_api_server');
  }

  /**
   * Saves this server to the database, either creating a new record or updating
   * an existing one.
   *
   * @param $op
   *   If the server is not newly inserted, this will be passed to
   *   hook_search_api_server_update().
   *
   * @return
   *   Failure to save the server will return FALSE. Otherwise, SAVED_NEW or
   *   SAVED_UPDATED is returned depending on the operation performed. $this->id
   *   will be set if a new server was inserted.
   */
  public function save($op = 'edit') {
    foreach (array('name', 'machine_name', 'class', 'options') as $var) {
      if (empty($this->$var)) {
        throw new SearchApiException(t('Property !field has to be set for a server!', array('!field' => $var)));
      }
    }
    if (empty($this->description)) {
      $this->description = NULL;
    }

    $ret = parent::save();
    if ($ret == SAVED_NEW) {
      $this->postCreate();
    }
    elseif ($ret == SAVED_UPDATED && $op != 'edit') {
      module_invoke_all('search_api_server_update', $this, $op);
    }

    return $ret;
  }

  /**
   * Wrapper for save() to correctly call postUpdate().
   *
   * NOTE: You shouldn't change any properties of this object before calling
   * this method, as this might lead to the fields not being saved correctly.
   *
   * @param array $fields
   *   The new field values. $this->enabled cannot be set this way, use
   *   $this->enable() instead.
   *
   * @return
   *   SAVE_UPDATED on success, FALSE on failure, 0 if the fields already had
   *   the specified values.
   */
  public function update(array $fields) {
    $changeable = array('name' => 1, 'description' => 1, 'options' => 1);
    foreach ($fields as $field => $value) {
      if (!isset($changeable[$field]) || $value === $this->$field) {
        unset($fields[$field]);
      }
    }
    // If there are no new values, just return 0.
    if (empty($fields)) {
      return 0;
    }
    $reindex = $this->postUpdate($fields);
    foreach ($fields as $field => $value) {
      $this->$field = $value;
    }
    $ret = $this->save('edit');
    if ($ret) {
      if ($reindex) {
        foreach (search_api_index_load_multiple(FALSE, array('server' => $this->machine_name)) as $index) {
          $index->reindex();
        }
      }
    }
    return $ret;
  }

  /**
   * Enables or disables this server.
   *
   * @param $enabled
   *   The new enabled status of the server.
   *
   * @return
   *   SAVE_UPDATED on success, FALSE on failure, 0 if the field already had
   *   the specified value.
   */
  public function enable($enabled = TRUE) {
    if ($this->enabled == $enabled) {
      return 0;
    }
    $this->enabled = $enabled;
    $ret = $this->save($enabled ? 'enable' : 'disable');
    if (!$ret) {
      return $ret;
    }

    if ($enabled) {
      $this->postEnable();
      // Were there any changes in the server's indexes while it was disabled?
      $tasks = variable_get('search_api_tasks', array());
      if (isset($tasks[$this->machine_name])) {
        foreach ($tasks[$this->machine_name] as $index_id => $index_tasks) {
          $index = search_api_index_load($index_id);
          foreach ($index_tasks as $task) {
            switch ($task) {
              case 'add':
                $this->addIndex($index);
                break;
              case 'clear':
                $this->deleteItems('all', $index);
                break;
              case 'clear all':
                // Would normally be used with a fake index ID of '', since it doesn't matter.
                $this->deleteItems('all');
                break;
              case 'fields':
                if ($this->fieldsUpdated($index)) {
                  _search_api_index_reindex($index->machine_name);
                }
                break;
              case 'remove':
                $this->removeIndex($index ? $index : $index_id);
                break;
              default:
                if (substr($task, 0, 7) == 'delete-') {
                  $id = substr($task, 7);
                  $this->deleteItems(array($id), $index);
                }
                else {
                  watchdog('search_api', t('Unknown task "!task" for server "!name".', array('!task' => $task, '!name' => $this->machine_name)), NULL, 'warning');
                }
            }
          }
        }
        unset($tasks[$this->machine_name]);
        variable_set('search_api_tasks', $tasks);
      }
    }
    else {
      $this->postDisable();
      foreach (search_api_index_load_multiple(FALSE, array('server' => $this->machine_name)) as $index) {
        $index->enable(FALSE);
      }
    }

    return $ret;
  }

  /**
   * Deletes this server.
   *
   * @return
   *   1 on success, 0 or FALSE on failure.
   */
  public function delete() {
    // Call hook method before doing anything else.
    $this->preDelete();

    foreach (search_api_index_load_multiple(FALSE, array('server' => $this->machine_name)) as $index) {
      $index->enable(FALSE);
      $index->update(array('server' => NULL));
      $index->reindex();
    }

    parent::delete();

    $tasks = variable_get('search_api_tasks', array());
    unset($tasks[$this->machine_name]);
    variable_set('search_api_tasks', $tasks);

    return 1;
  }

  /**
   * Magic method for determining which fields should be serialized.
   *
   * Serialize all properties except the proxy object.
   *
   * @return array
   *   An array of properties to be serialized.
   */
  public function __sleep() {
    $ret = parent::__sleep();
    if (($pos = array_search('proxy', $ret)) !== FALSE) {
      unset($ret[$pos]);
    }
    return $ret;
  }

  /**
   * Helper method for ensuring the proxy object is set up.
   */
  protected function ensureProxy() {
    if (!isset($this->proxy)) {
      $class = search_api_get_service_info($this->class);
      if ($class && class_exists($class['class'])) {
        if (empty($this->options)) {
          // We always have to provide the options.
          $this->options = array();
        }
        $this->proxy = new $class['class']($this);
      }
      if (!($this->proxy instanceof SearchApiServiceInterface)) {
        throw new SearchApiException(t('Search server with machine name !name specifies illegal service class !class.', array('!name' => $this->machine_name, '!class' => $this->class)));
      }
    }
  }

  /**
   * If the service class defines additional methods, not specified in the
   * SearchApiServiceInterface interface, then they are called via this magic
   * method.
   */
  public function __call($name, $arguments = array()) {
    $this->ensureProxy();
    return call_user_func_array(array($this->proxy, $name), $arguments);
  }

  // Proxy methods

  // For increased clarity, and since some parameters are passed by reference,
  // we don't use the __call() magic method for those.

  public function configurationForm(array $form, array &$form_state) {
    $this->ensureProxy();
    return $this->proxy->configurationForm($form, $form_state);
  }

  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    $this->ensureProxy();
    return $this->proxy->configurationFormValidate($form, $values, $form_state);
  }

  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    $this->ensureProxy();
    return $this->proxy->configurationFormSubmit($form, $values, $form_state);
  }

  public function supportsFeature($feature) {
    $this->ensureProxy();
    return $this->proxy->supportsFeature($feature);
  }

  public function viewSettings() {
    $this->ensureProxy();
    return $this->proxy->viewSettings();
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
  protected $processors = NULL;

  /**
   * @var array
   */
  protected $added_properties = array();

  /**
   * @var array
   */
  protected $fulltext_fields = array();

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
   * @var string
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
   * Execute necessary tasks for a newly created index (either created in the
   * database, or for the first time loaded from code).
   */
  public function postCreate() {
    $entities = entity_load($this->entity_type, FALSE);
    $query = db_insert('search_api_item')->fields(array('item_id', 'index_id', 'changed'));
    foreach ($entities as $item_id => $entity) {
      $query->values(array(
        'item_id' => $item_id,
        'index_id' => $this->machine_name,
        'changed' => 1,
      ));
    }
    $query->execute();

    $server = $this->server();
    if ($server) {
      if ($server->enabled) {
        $server->addIndex($this);
      }
      else {
        $tasks = variable_get('search_api_tasks', array());
        // When we add or remove an index, we can ignore all other tasks.
        $tasks[$server->machine_name][$this->machine_name] = array('add');
        variable_set('search_api_tasks', $tasks);
      }
    }
  }

  /**
   * Execute necessary tasks when index is either deleted from the database or
   * not defined in code anymore.
   */
  public function postDelete() {
    if ($server = $this->server()) {
      if ($server->enabled) {
        $server->removeIndex($this);
      }
      else {
        $tasks = variable_get('search_api_tasks', array());
        $tasks[$server->machine_name][$this->machine_name] = array('remove');
        variable_set('search_api_tasks', $tasks);
      }
    }

    db_delete('search_api_item')
      ->condition('index_id', $this->machine_name)
      ->execute();
  }

  /**
   * Saves this index to the database, either creating a new record or updating
   * an existing one.
   *
   * @param $op
   *   If the index is not newly inserted, this will be passed to
   *   hook_search_api_index_update().
   *
   * @return
   *   Failure to save the index will return FALSE. Otherwise, SAVED_NEW or
   *   SAVED_UPDATED is returned depending on the operation performed. $this->id
   *   will be set if a new index was inserted.
   */
  public function save($op = 'edit') {
    foreach (array('name', 'machine_name', 'entity_type', 'options') as $var) {
      if (empty($this->$var)) {
        throw new SearchApiException(t('Property !field has to be set for an index!', array('!field' => $var)));
      }
    }
    if (empty($this->description)) {
      $this->description = NULL;
    }
    if (empty($this->server)) {
      $this->server = NULL;
      $this->enabled = FALSE;
    }
    // This will also throw an exception if the server doesn't exist – which is good.
    elseif (!$this->server()->enabled) {
      $this->enabled = FALSE;
    }

    $ret = parent::save();
    // We only have to execute the "new index" tasks when the entity was newly created, not overridden.
    if ($ret == SAVED_NEW && (!isset($this->status) || !($this->status & ENTITY_IN_CODE))) {
      $this->postCreate();
    }
    elseif ($ret == SAVED_UPDATED && $op != 'edit') {
      module_invoke_all('search_api_index_update', $this, $op);
    }

    return $ret;
  }

  /**
   * Wrapper for save() to correctly call hooks.
   *
   * NOTE: You shouldn't change any properties of this object before calling
   * this method, as this might lead to the fields not being saved correctly.
   *
   * @param array $fields
   *   The new field values. $this->enabled cannot be set this way, use
   *   $this->enable() instead.
   *
   * @return
   *   SAVE_UPDATED on success, FALSE on failure, 0 if the fields already had
   *   the specified values.
   */
  public function update(array $fields) {
    $changeable = array('name' => 1, 'description' => 1, 'server' => 1, 'options' => 1);
    foreach ($fields as $field => $value) {
      if (!isset($changeable[$field]) || $value === $this->$field) {
        unset($fields[$field]);
      }
    }
    // If there are no new values, just return 0.
    if (empty($fields)) {
      return 0;
    }

    if (array_key_exists('server', $fields)) {
      // Server changed - inform old and new ones.
      if ($this->server) {
        $old_server = $this->server();
        if ($old_server->enabled) {
          $old_server->removeIndex($this);
        }
        else {
          $tasks = variable_get('search_api_tasks', array());
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$old_server->machine_name][$this->machine_name] = array('remove');
          variable_set('search_api_tasks', $tasks);
        }
      }

      if ($fields['server']) {
        $new_server = search_api_server_load($fields['server']);
        // If the server is disabled, we save the task. Otherwise we call addIndex() after saving the entry.
        if (!$new_server->enabled) {
          if ($this->enabled) {
            $fields['enabled'] = 0;
          }
          $tasks = variable_get('search_api_tasks', array());
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$new_server->machine_name][$this->machine_name] = array('add');
          variable_set('search_api_tasks', $tasks);
          unset($new_server);
        }
      }
      elseif ($this->enabled) {
        $fields['enabled'] = 0;
      }

      // We also have to re-index all content
      unset($this->server_object);
      _search_api_index_reindex($this->machine_name);
    }

    // Determine whether the indexed fields were changed for later calling appropriate hooks.
    $fields_changed = isset($fields['options']['fields'])
        && isset($this->options['fields'])
        && $fields['options']['fields'] != $this->options['fields'];
    foreach ($fields as $field => $value) {
      $this->$field = $value;
    }
    $ret = parent::save('edit');
    if (!$ret) {
      return $ret;
    }

    if (isset($new_server)) {
      $new_server->addIndex($this);
    }
    if ($fields_changed) {
      if ($this->server && $this->server()->fieldsUpdated($this)) {
        _search_api_index_reindex($this->machine_name);
      }
      module_invoke_all('search_api_index_update', $this, 'fields');
    }

    return $ret;
  }

  /**
   * Enables or disables this index.
   *
   * @param $enabled
   *   The new enabled status of the index.
   *
   * @throws SearchApiException
   *   If $enabled is TRUE and the index' server isn't enabled.
   *
   * @return
   *   SAVE_UPDATED on success, FALSE on failure, 0 if the field already had
   *   the specified value.
   */
  public function enable($enabled = TRUE) {
    if ($this->enabled == $enabled) {
      return 0;
    }
    if ($enabled && !($this->server() && $this->server()->enabled)) {
      throw new SearchApiException(t("Attempt to enable index !name which isn't on an enabled server.", array('!name' => $this->machine_name)));
    }
    $this->enabled = $enabled;
    return $this->save($enabled ? 'enable' : 'disable');
  }

  /**
   * Schedules this search index for re-indexing.
   *
   * @return
   *   TRUE on success, FALSE on failure.
   */
  public function reindex() {
    if (!$this->server) {
      return TRUE;
    }
    $ret = _search_api_index_reindex($this->machine_name);
    if($ret) {
      module_invoke_all('search_api_index_update', $this, 'reindex');
    }
    return TRUE;
  }

  /**
   * Clears this search index and schedules all of its items for re-indexing.
   *
   * @return
   *   TRUE on success, FALSE on failure.
   */
  public function clear() {
    if (!$this->server) {
      return TRUE;
    }

    $server = $this->server();
    if ($server->enabled) {
      $server->deleteItems('all', $this);
    }
    else {
      $tasks = variable_get('search_api_tasks', array());
      // If the index was cleared or newly added since the server was last enabled, we don't need to do anything.
      if (!isset($tasks[$server->machine_name][$id])
          || (array_search('add', $tasks[$server->machine_name][$id]) === FALSE
              && array_search('clear', $tasks[$server->machine_name][$id]) === FALSE)) {
        $tasks[$server->machine_name][$id][] = 'clear';
        variable_set('search_api_tasks', $tasks);
      }
    }

    $ret = _search_api_index_reindex($this->machine_name);
    if($ret) {
      module_invoke_all('search_api_index_update', $this, 'clear');
    }

    return TRUE;
  }

  /**
   * Deletes this search index.
   *
   * @return
   *   TRUE on success, FALSE on failure.
   */
  public function delete() {
    parent::delete();
    $this->postDelete();
    return TRUE;
  }

  /**
   * Magic method for determining which fields should be serialized.
   *
   * Don't serialize properties that are basically only caches.
   *
   * @return array
   *   An array of properties to be serialized.
   */
  public function __sleep() {
    $ret = parent::__sleep();
    $ignore = array('server_object' => 1, 'processors' => 1, 'added_properties' => 1, 'fulltext_fields' => 1);
    foreach ($ret as $i => $prop) {
      if (isset($ignore[$prop])) {
        unset($ret[$i]);
      }
    }
    return $ret;
  }

  /**
   * Get the server this index lies on.
   *
   * @throws SearchApiException
   *   If $this->server is set, but no server with that machine name exists.
   *
   * @return SearchApiServer
   *   The server associated with this index, or NULL if this index currently
   *   doesn't lie on a server.
   */
  public function server() {
    if (!isset($this->server_object)) {
      $this->server_object = $this->server ? search_api_server_load($this->server) : FALSE;
      if ($this->server && !$this->server_object) {
        throw new SearchApiException(t('Unknown server !server specified for index !name.',
            array('!server' => $this->server, '!name' => $this->machine_name)));
      }
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
    // Overwrite the existing properties with the list of properties including
    // all fields regardless of the used bundle.
    $property_info['properties'] = entity_metadata_get_properties($wrapper->type());

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
    if (empty($this->options['processors']) || isset($this->processors)) {
      return $this;
    }

    $this->processors = array();
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
    $i = $only_indexed ? 1 : 0;
    if (!isset($this->fulltext_fields[$i])) {
      $this->fulltext_fields[$i] = array();
      if (empty($this->options['fields'])) {
        return array();
      }
      foreach ($this->options['fields'] as $key => $field) {
        if (search_api_is_text_type($field['type']) && (!$only_indexed || $field['indexed'])) {
          $this->fulltext_fields[$i][] = $key;
        }
      }
    }
    return $this->fulltext_fields[$i];
  }

}
