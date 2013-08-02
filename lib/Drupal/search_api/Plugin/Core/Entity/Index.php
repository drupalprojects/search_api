<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\Core\Entity\Index.
 */

namespace Drupal\search_api\Plugin\Core\Entity;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\ProcessorInterface;
use Drupal\search_api\Plugin\search_api\QueryInterface;
use Drupal\search_api\SearchApiException;

/**
 * Defines a search index configuration entity class.
 *
 * @EntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   module = "search_api",
 *   controllers = {
 *     "storage" = "Drupal\search_api\IndexStorageController",
 *     "access" = "Drupal\search_api\IndexAccessController",
 *     "render" = "Drupal\search_api\IndexRenderController",
 *     "form" = {
 *       "default" = "Drupal\search_api\IndexFormController",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteForm"
 *     }
 *   },
 *   config_prefix = "search_api.index",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "enabled"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search_api/index/{search_api_index}",
 *     "edit-form" = "/admin/config/search/search_api/index/{search_api_index}/edit",
 *   }
 * )
 */
class Index extends ConfigEntityBase implements IndexInterface {

  // Database values that will be set when object is loaded.

  /**
   * The machine name of the index.
   *
   * @var string
   */
  public $machine_name;

  /**
   * A name to be displayed for the index.
   *
   * @var string
   */
  public $name;

  /**
   * A Universally Unique Identifier for the index.
   *
   * @var string
   */
  public $uuid;

  /**
   * A string describing the index' use to users.
   *
   * @var string
   */
  public $description;

  /**
   * The machine_name of the server with which data should be indexed.
   *
   * @var string
   */
  public $server;

  /**
   * The type of items stored in this index.
   *
   * @var string
   */
  public $item_type;

  /**
   * An array of options for configuring this index. The layout is as follows:
   * - cron_limit: The maximum number of items to be indexed per cron batch.
   * - index_directly: Boolean setting whether entities are indexed immediately
   *   after they are created or updated.
   * - fields: An array of all indexed fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: (optional) A boost value for terms found in this field during
   *     searches. Usually only relevant for fulltext fields. Defaults to 1.0.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then just contain the primitive data type
   *     of the ID field, meaning that servers will ignore this and merely index
   *     the entity's ID. Components displaying this field, though, are advised
   *     to use the entity label instead of the ID.
   * - additional_fields: An associative array with keys and values being the
   *   field identifiers of related entities whose fields should be displayed.
   * - data_alter_callbacks: An array of all data alterations available. Keys
   *   are the alteration identifiers, the values are arrays containing the
   *   settings for that data alteration. The inner structure looks like this:
   *   - status: Boolean indicating whether the data alteration is enabled.
   *   - weight: Used for sorting the data alterations.
   *   - settings: Alteration-specific settings, configured via the alteration's
   *     configuration form.
   * - processors: An array of all processors available for the index. The keys
   *   are the processor identifiers, the values are arrays containing the
   *   settings for that processor. The inner structure looks like this:
   *   - status: Boolean indicating whether the processor is enabled.
   *   - weight: Used for sorting the processors.
   *   - settings: Processor-specific settings, configured via the processor's
   *     configuration form.
   *
   * @var array
   */
  public $options = array();

  /**
   * A flag indicating whether this index is enabled.
   *
   * @var integer
   */
  public $enabled = 1;

  /**
   * A flag indicating whether to write to this index.
   *
   * @var integer
   */
  public $read_only = 0;

  /**
   * The language this index was created in.
   *
   * @var string
   */
  public $langcode;

  /**
   * The old entity version, when saving an update.
   *
   * @var \Drupal\search_api\IndexInterface|null
   */
  public $original;

  // Cache values, set when the corresponding methods are called for the first
  // time.

  /**
   * Cached return value of datasource().
   *
   * @var \Drupal\search_api\Plugin\search_api\DatasourceInterface
   */
  protected $datasource = NULL;

  /**
   * Cached return value of server().
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $serverObject = NULL;

  /**
   * All enabled data alterations for this index.
   *
   * @var array
   */
  protected $callbacks = NULL;

  /**
   * All enabled processors for this index.
   *
   * @var array
   */
  protected $processors = NULL;

  /**
   * The properties added by data alterations on this index.
   *
   * @var array
   */
  protected $addedProperties = NULL;

  /**
   * Static cache for the results of getFields().
   *
   * Can be accessed as follows: $this->fields[$only_indexed][$get_additional].
   *
   * @var array
   */
  protected $fields = array();

  /**
   * An array containing two arrays.
   *
   * At index 0, all fulltext fields of this index. At index 1, all indexed
   * fulltext fields of this index.
   *
   * @var array
   */
  protected $fulltextFields = array();

  /**
   * {@inheritdoc}
   */
  public function id() {
    return isset($this->machine_name) ? $this->machine_name : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function uri() {
    return array(
      'path' => 'admin/config/search/search_api/index/' . $this->id(),
      'options' => array(
        'entity_type' => $this->entityType,
        'entity' => $this,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function readOnly() {
    return $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function setReadOnly($read_only) {
    $this->read_only = $read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemType() {
    return $this->item_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->datasource()->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  public function datasource() {
    if (!isset($this->datasource)) {
      $this->datasource = search_api_get_datasource_controller($this->item_type);
    }
    return $this->datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function serverId() {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function server($reset = FALSE) {
    if (!isset($this->serverObject) || $reset) {
      $this->serverObject = $this->server ? search_api_server_load($this->server) : FALSE;
      if ($this->server && !$this->serverObject) {
        throw new SearchApiException(t('Unknown server @server specified for index @name.', array('@server' => $this->server, '@name' => $this->machine_name)));
      }
    }
    return $this->serverObject ? $this->serverObject : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Overrides \Drupal\Core\Config\Entity\ConfigEntityBase::preSave().
   *
   * Corrects some settings with specific restrictions.
   */
  public function preSave(EntityStorageControllerInterface $storage_controller) {
    parent::preSave($storage_controller);

    if (empty($this->description)) {
      $this->description = NULL;
    }
    if (empty($this->server)) {
      $this->server = NULL;
      $this->enabled = FALSE;
    }
    // This will also throw an exception if the server doesn't exist – which is good.
    elseif (!$this->server(TRUE)->status()) {
      $this->enabled = FALSE;
    }
  }

  /**
   * Overrides \Drupal\Core\Entity\Entity::postSave().
   *
   * Executes necessary tasks for newly created indexes.
   */
  public function postSave(EntityStorageControllerInterface $storage_controller, $update = TRUE) {
    parent::postSave($storage_controller, $update);

    if (!$update) {
      if ($this->enabled) {
        $this->queueItems();
      }
      $server = $this->server();
      if ($server) {
        // Tell the server about the new index.
        if ($server->status()) {
          $server->addIndex($this);
        }
        else {
          $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$server->id()][$this->id()] = array('add');
          \Drupal::state()->set('search_api_tasks', $tasks);
        }
      }
    }
    else {
      $this->postUpdate();
    }
  }

  /**
   * Handles updates of this index.
   *
   * Called from postSave() if it was an update.
   */
  protected function postUpdate() {
    // Reset the index's internal property cache to correctly incorporate new
    // settings.
    $this->resetCaches();

    // If the server was changed, we have to call the appropriate service class
    // hook methods.
    if ($this->server != $this->original->serverId()) {
      // Server changed - inform old and new ones.
      if ($this->original->serverId()) {
        $old_server = search_api_server_load($this->original->serverId());
        // The server might have changed because the old one was deleted:
        if ($old_server) {
          if ($old_server->status()) {
            $old_server->removeIndex($this);
          }
          else {
            $tasks = \Drupal::state()->get('search_api_tasks') ? : array();
            // When we add or remove an index, we can ignore all other tasks.
            $tasks[$old_server->id()][$this->id()] = array('remove');
            \Drupal::state()->set('search_api_tasks', $tasks);
          }
        }
      }

      if ($this->server) {
        $new_server = $this->server(TRUE);
        // If the server is enabled, we call addIndex(); otherwise, we save the task.
        if ($new_server->status()) {
          $new_server->addIndex($this);
        }
        else {
          $tasks = \Drupal::state()->get('search_api_tasks') ? : array();
          // When we add or remove an index, we can ignore all other tasks.
          $tasks[$new_server->id()][$this->id()] = array('add');
          \Drupal::state()->set('search_api_tasks', $tasks);
          unset($new_server);
        }
      }

      // We also have to re-index all content.
      _search_api_index_reindex($this);
    }

    // If the fields were changed, call the appropriate service class hook
    // method and re-index the content, if necessary. Also, clear the fields
    // cache.
    $old_fields = $this->original->getOption('fields', array());
    $new_fields = $this->getOption('fields', array());
    if ($old_fields != $new_fields) {
      $this->cache()->deleteTags(array('search_api_index-fields' => $this->id()));
      if ($this->server && $this->server()->fieldsUpdated($this)) {
        _search_api_index_reindex($this);
      }
    }

    // If additional fields changed, clear the index's specific cache which
    // includes them.
    $old_additional = $this->original->getOption('additional_fields', array());
    $new_additional = $this->getOption('additional_fields', array());
    if ($old_additional != $new_additional) {
      $this->cache()->delete($this->getCacheId() . '-0-1');
    }

    // We only index (and, therefore, track) items if an index is enabled and
    // not read-only. If this combined state changed, we have to start/stop
    // tracking.
    $track_old = $this->original->status() && $this->original->readOnly();
    $track_new = $this->status() && $this->readOnly();
    if ($track_old != $track_new) {
      if ($track_new) {
        $this->queueItems();
      }
      else {
        $this->dequeueItems();
      }
    }

    // If the cron batch size changed, empty the cron queue for this index.
    $old_cron = $this->original->getOption('cron_limit');
    $new_cron = $this->getOption('cron_limit');
    if ($old_cron !== $new_cron) {
      _search_api_empty_cron_queue($this, TRUE);
    }
  }

  /**
   * Overrides \Drupal\Core\Entity\Entity::postDelete().
   *
   * Executes necessary tasks when the index is removed from the database.
   */
  public static function postDelete(EntityStorageControllerInterface $storage_controller, array $entities) {
    foreach ($entities as $index) {
      if ($server = $index->server()) {
        if ($server->status()) {
          $server->removeIndex($index);
        }
        // Once the index is deleted, servers won't be able to tell whether it was
        // read-only. Therefore, we prefer to err on the safe side and don't call
        // the server method at all if the index is read-only and the server
        // currently disabled.
        elseif (!$index->read_only()) {
          $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
          $tasks[$server->id()][$index->id()] = array('remove');
          \Drupal::state()->set('search_api_tasks', $tasks);
        }
      }

      // Stop tracking entities for indexing.
      $index->dequeueItems();

      // Delete index's cache.
      $index->cache()->deleteTags(array('search_api_index' => $index->id()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function queueItems() {
    if (!$this->read_only) {
      $this->datasource()->startTracking(array($this));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dequeueItems() {
    $this->datasource()->stopTracking(array($this));
    _search_api_empty_cron_queue($this);
  }

  /**
   * {@inheritdoc}
   */
  public function reindex() {
    if (!$this->server || $this->read_only) {
      return TRUE;
    }
    _search_api_index_reindex($this);
    \Drupal::moduleHandler()->invokeAll('search_api_index_reindex', array($this, FALSE));
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    if (!$this->server || $this->read_only) {
      return TRUE;
    }

    $server = $this->server();
    if ($server->status()) {
      $server->deleteItems('all', $this);
    }
    else {
      $tasks = \Drupal::state()->get('search_api_tasks') ?: array();
      // If the index was cleared or newly added since the server was last
      // enabled, we don't need to do anything.
      if (!isset($tasks[$server->id()][$this->id()])
          || (array_search('add', $tasks[$server->id()][$this->id()]) === FALSE
              && array_search('clear', $tasks[$server->id()][$this->id()]) === FALSE)) {
        $tasks[$server->id()][$this->id()][] = 'clear';
        \Drupal::state()->set('search_api_tasks', $tasks);
      }
    }

    _search_api_index_reindex($this);
    \Drupal::moduleHandler()->invokeAll('search_api_index_reindex', array($this, TRUE));
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
    $ret = get_object_vars($this);
    unset($ret['server_object'], $ret['datasource'], $ret['processors'], $ret['added_properties'], $ret['fulltext_fields']);
    return array_keys($ret);
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $options = array()) {
    if (!$this->enabled) {
      throw new SearchApiException(t('Cannot search on a disabled index.'));
    }
    return search_api_query($this->id(), $options);
  }

  /**
   * {@inheritdoc}
   */
  public function index(array $items) {
    if ($this->read_only) {
      return array();
    }
    if (!$this->enabled) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (index is disabled)", array('@name' => $this->name)));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }
    $fields = $this->options['fields'];
    $custom_type_fields = array();
    foreach ($fields as $field => $info) {
      if (isset($info['real_type'])) {
        $custom_type = search_api_extract_inner_type($info['real_type']);
        if ($this->server()->supportsFeature('search_api_data_type_' . $custom_type)) {
          $fields[$field]['type'] = $info['real_type'];
          $custom_type_fields[$custom_type][$field] = search_api_list_nesting_level($info['real_type']);
        }
      }
    }
    if (empty($fields)) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }

    // Mark all items that are rejected as indexed.
    $ret = array_keys($items);
    \Drupal::moduleHandler()->alter('search_api_index_items', $items, $this);
    $ret = array_diff($ret, array_keys($items));

    // Items that are rejected should also be deleted from the server.
    if ($ret) {
      $this->server()->deleteItems($ret, $this);
    }
    if (!$items) {
      return $ret;
    }

    $data = array();
    foreach ($items as $id => $item) {
      $data[$id] = search_api_extract_fields($this->entityWrapper($item), $fields);
      unset($items[$id]);
      foreach ($custom_type_fields as $type => $type_fields) {
        $info = search_api_get_data_type_info($type);
        if (isset($info['conversion callback']) && is_callable($info['conversion callback'])) {
          $callback = $info['conversion callback'];
          foreach ($type_fields as $field => $nesting_level) {
            if (isset($data[$id][$field]['value'])) {
              $value = $data[$id][$field]['value'];
              $original_type = $data[$id][$field]['original_type'];
              $data[$id][$field]['value'] = _search_api_convert_custom_type($callback, $value, $original_type, $type, $nesting_level);
            }
          }
        }
      }
    }

    $this->preprocessIndexItems($data);

    return array_merge($ret, $this->server()->indexItems($this, $data));
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfoAlter(ComplexDataInterface $wrapper, array $property_info) {
    // @todo Adapt to ComplexDataInterface.
    if (entity_get_property_info($wrapper->type())) {
      // Overwrite the existing properties with the list of properties including
      // all fields regardless of the used bundle.
      $property_info['properties'] = entity_get_all_property_info($wrapper->type());
    }

    if (!isset($this->addedProperties)) {
      $this->addedProperties = array(
        'search_api_language' => array(
          'label' => t('Item language'),
          'description' => t("A field added by the search framework to let components determine an item's language. Is always indexed."),
          'type' => 'token',
          'options list' => 'entity_metadata_language_list',
        ),
      );
      // We use the reverse order here so the hierarchy for overwriting property
      // infos is the same as for actually overwriting the properties.
      foreach (array_reverse($this->getAlterCallbacks()) as $callback) {
        $props = $callback->propertyInfo();
        if ($props) {
          $this->addedProperties += $props;
        }
      }
    }
    // Let fields added by data-alter callbacks override default fields.
    $property_info['properties'] = array_merge($property_info['properties'], $this->addedProperties);

    return $property_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessors() {
    if (isset($this->processors)) {
      return $this->processors;
    }

    $this->processors = array();
    if (empty($this->options['processors'])) {
      return $this->processors;
    }
    $processor_settings = $this->options['processors'];
    $infos = search_api_get_processors();

    foreach ($processor_settings as $id => $settings) {
      if (empty($settings['status'])) {
        continue;
      }
      if (empty($infos[$id]) || !class_exists($infos[$id]['class'])) {
        watchdog('search_api', t('Undefined processor @class specified in index @name', array('@class' => $id, '@name' => $this->name)), NULL, WATCHDOG_WARNING);
        continue;
      }
      $class = $infos[$id]['class'];
      $processor = new $class($this, isset($settings['settings']) ? $settings['settings'] : array());
      if (!($processor instanceof ProcessorInterface)) {
        watchdog('search_api', t('Unknown processor class @class specified for processor @name', array('@class' => $class, '@name' => $id)), NULL, WATCHDOG_WARNING);
        continue;
      }

      $this->processors[$id] = $processor;
    }
    return $this->processors;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessIndexItems($items);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessSearchQuery($query);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query) {
    // Postprocessing is done in exactly the opposite direction than preprocessing.
    foreach (array_reverse($this->getProcessors()) as $processor) {
      $processor->postprocessSearchResults($response, $query);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($only_indexed = TRUE, $get_additional = FALSE) {
    // @todo Adapt to ComplexDataInterface.
    $only_indexed = $only_indexed ? 1 : 0;
    $get_additional = $get_additional ? 1 : 0;

    // First, try the static cache and the persistent cache bin.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $cid = $this->getCacheId() . "-$only_indexed-$get_additional";
      $cache = $this->cache()->get($cid);
      if ($cache) {
        $this->fields[$only_indexed][$get_additional] = $cache->data;
      }
    }

    // Otherwise, we have to compute the result.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $fields = empty($this->options['fields']) ? array() : $this->options['fields'];
      $wrapper = $this->entityWrapper();
      $additional = array();
      $entity_types = entity_get_info();

      // First we need all already added prefixes.
      $added = ($only_indexed || empty($this->options['additional_fields'])) ? array() : $this->options['additional_fields'];
      foreach (array_keys($fields) as $key) {
        $len = strlen($key) + 1;
        $pos = $len;
        // The third parameter ($offset) to strrpos has rather weird behaviour,
        // necessitating this rather awkward code. It will iterate over all
        // prefixes of each field, beginning with the longest, adding all of them
        // to $added until one is encountered that was already added (which means
        // all shorter ones will have already been added, too).
        while ($pos = strrpos($key, ':', $pos - $len)) {
          $prefix = substr($key, 0, $pos);
          if (isset($added[$prefix])) {
            break;
          }
          $added[$prefix] = $prefix;
        }
      }

      // Then we walk through all properties and look if they are already
      // contained in one of the arrays.
      // Since this uses an iterative instead of a recursive approach, it is a bit
      // complicated, with three arrays tracking the current depth.

      // A wrapper for a specific field name prefix, e.g. 'user:' mapped to the user wrapper
      $wrappers = array('' => $wrapper);
      // Display names for the prefixes
      $prefix_names = array('' => '');
        // The list nesting level for entities with a certain prefix
      $nesting_levels = array('' => 0);

      $types = search_api_default_field_types();
      $flat = array();
      while ($wrappers) {
        foreach ($wrappers as $prefix => $wrapper) {
          $prefix_name = $prefix_names[$prefix];
          // Deal with lists of entities.
          $nesting_level = $nesting_levels[$prefix];
          $type_prefix = str_repeat('list<', $nesting_level);
          $type_suffix = str_repeat('>', $nesting_level);
          if ($nesting_level) {
            $info = $wrapper->info();
            // The real nesting level of the wrapper, not the accumulated one.
            $level = search_api_list_nesting_level($info['type']);
            for ($i = 0; $i < $level; ++$i) {
              $wrapper = $wrapper[0];
            }
          }
          // Now look at all properties.
          foreach ($wrapper as $property => $value) {
            $info = $value->info();
            // We hide the complexity of multi-valued types from the user here.
            $type = search_api_extract_inner_type($info['type']);
            // Treat Entity API type "token" as our "string" type.
            // Also let text fields with limited options be of type "string" by default.
            if ($type == 'token' || ($type == 'text' && !empty($info['options list']))) {
              // Inner type is changed to "string".
              $type = 'string';
              // Set the field type accordingly.
              $info['type'] = search_api_nest_type('string', $info['type']);
            }
            $info['type'] = $type_prefix . $info['type'] . $type_suffix;
            $key = $prefix . $property;
            if ((isset($types[$type]) || isset($entity_types[$type])) && (!$only_indexed || !empty($fields[$key]))) {
              if (!empty($fields[$key])) {
                // This field is already known in the index configuration.
                $flat[$key] = $fields[$key] + array(
                  'name' => $prefix_name . $info['label'],
                  'description' => empty($info['description']) ? NULL : $info['description'],
                  'boost' => '1.0',
                  'indexed' => TRUE,
                );
                // Update the type and its nesting level for non-entity properties.
                if (!isset($entity_types[$type])) {
                  $flat[$key]['type'] = search_api_nest_type(search_api_extract_inner_type($flat[$key]['type']), $info['type']);
                  if (isset($flat[$key]['real_type'])) {
                    $real_type = search_api_extract_inner_type($flat[$key]['real_type']);
                    $flat[$key]['real_type'] = search_api_nest_type($real_type, $info['type']);
                  }
                }
              }
              else {
                $flat[$key] = array(
                  'name'    => $prefix_name . $info['label'],
                  'description' => empty($info['description']) ? NULL : $info['description'],
                  'type'    => $info['type'],
                  'boost' => '1.0',
                  'indexed' => FALSE,
                );
              }
              if (isset($entity_types[$type])) {
                $base_type = isset($entity_types[$type]['entity keys']['name']) ? 'string' : 'integer';
                $flat[$key]['type'] = search_api_nest_type($base_type, $info['type']);
                $flat[$key]['entity_type'] = $type;
              }
            }
            if (empty($types[$type])) {
              if (isset($added[$key])) {
                // Visit this entity/struct in a later iteration.
                $wrappers[$key . ':'] = $value;
                $prefix_names[$key . ':'] = $prefix_name . $info['label'] . ' » ';
                $nesting_levels[$key . ':'] = search_api_list_nesting_level($info['type']);
              }
              else {
                $name = $prefix_name . $info['label'];
                // Add machine names to discern fields with identical labels.
                if (isset($used_names[$name])) {
                  if ($used_names[$name] !== FALSE) {
                    $additional[$used_names[$name]] .= ' [' . $used_names[$name] . ']';
                    $used_names[$name] = FALSE;
                  }
                  $name .= ' [' . $key . ']';
                }
                $additional[$key] = $name;
                $used_names[$name] = $key;
              }
            }
          }
          unset($wrappers[$prefix]);
        }
      }

      if (!$get_additional) {
        $this->fields[$only_indexed][$get_additional] = $flat;
      }
      else {
        $options = array();
        $options['fields'] = $flat;
        $options['additional_fields'] = $additional;
        $this->fields[$only_indexed][$get_additional] =  $options;
      }
      $tags['search_api_index'] = $this->id();
      $tags['search_api_index-fields'] = $this->id();
      $this->cache()->set($cid, $this->fields[$only_indexed][$get_additional],
          CacheBackendInterface::CACHE_PERMANENT, $tags);
    }

    return $this->fields[$only_indexed][$get_additional];
  }

  /**
   * Convenience method for getting all of this index's fulltext fields.
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
    if (!isset($this->fulltextFields[$i])) {
      $this->fulltextFields[$i] = array();
      $fields = $only_indexed ? $this->options['fields'] : $this->getFields(FALSE);
      foreach ($fields as $key => $field) {
        if (search_api_is_text_type($field['type'])) {
          $this->fulltextFields[$i][] = $key;
        }
      }
    }
    return $this->fulltextFields[$i];
  }

  /**
   * {@inheritdoc}
   */
  public function cache() {
    return cache();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheId($type = 'fields') {
    return 'search_api:index-' . $this->machine_name . '--' . $type;
  }

  /**
   * {@inheritdoc}
   */
  public function entityWrapper($item = NULL, $alter = TRUE) {
    $info['property info alter'] = $alter ? array($this, 'propertyInfoAlter') : '_search_api_wrapper_add_all_properties';
    $info['property defaults']['property info alter'] = '_search_api_wrapper_add_all_properties';
    return $this->datasource()->getMetadataWrapper($item, $info);
  }

  /**
   * {@inheritdoc}
   */
  public function loadItems(array $ids) {
    return $this->datasource()->loadItems($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCaches() {
    $this->datasource = NULL;
    $this->serverObject = NULL;
    $this->callbacks = NULL;
    $this->processors = NULL;
    $this->addedProperties = NULL;
    $this->fields = array();
    $this->fulltextFields = array();
  }

}
