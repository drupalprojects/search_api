<?php

/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api\Utility;
use Drupal\user\TempStoreException;
use Drupal\views\Views;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\search_api\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\IndexForm",
 *       "edit" = "Drupal\search_api\Form\IndexForm",
 *       "fields" = "Drupal\search_api\Form\IndexFieldsForm",
 *       "add_fields" = "Drupal\search_api\Form\IndexAddFieldsForm",
 *       "break_lock" = "Drupal\search_api\Form\IndexBreakLockForm",
 *       "processors" = "Drupal\search_api\Form\IndexProcessorsForm",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm",
 *       "reindex" = "Drupal\search_api\Form\IndexReindexConfirmForm",
 *       "clear" = "Drupal\search_api\Form\IndexClearConfirmForm"
 *     },
 *   },
 *   admin_permission = "administer search_api",
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "read_only",
 *     "fields",
 *     "processors",
 *     "options",
 *     "datasources",
 *     "datasource_configs",
 *     "tracker",
 *     "tracker_config",
 *     "server",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/search-api/index/{search_api_index}",
 *     "add-form" = "/admin/config/search/search-api/add-index",
 *     "edit-form" = "/admin/config/search/search-api/index/{search_api_index}/edit",
 *     "fields" = "/admin/config/search/search-api/index/{search_api_index}/fields",
 *     "add-fields" = "/admin/config/search/search-api/index/{search_api_index}/fields/add",
 *     "break-lock-form" = "/admin/config/search/search-api/index/{search_api_index}/fields/break-lock",
 *     "processors" = "/admin/config/search/search-api/index/{search_api_index}/processors",
 *     "delete-form" = "/admin/config/search/search-api/index/{search_api_index}/delete",
 *     "disable" = "/admin/config/search/search-api/index/{search_api_index}/disable",
 *     "enable" = "/admin/config/search/search-api/index/{search_api_index}/enable",
 *   }
 * )
 */
class Index extends ConfigEntityBase implements IndexInterface {

  /**
   * The ID of the index.
   *
   * @var string
   */
  protected $id;

  /**
   * A name to be displayed for the index.
   *
   * @var string
   */
  protected $name;

  /**
   * A string describing the index.
   *
   * @var string
   */
  protected $description;

  /**
   * A flag indicating whether to write to this index.
   *
   * @var bool
   */
  protected $read_only = FALSE;

  /**
   * An array of field settings.
   *
   * @var array
   */
  protected $fields = array();

  /**
   * An array of options configuring this index.
   *
   * @var array
   *
   * @see getOptions()
   */
  protected $options = array();

  /**
   * The IDs of the datasources selected for this index.
   *
   * @var string[]
   */
  protected $datasources = array();

  /**
   * The configuration for the selected datasources.
   *
   * @var array
   */
  protected $datasource_configs = array();

  /**
   * The instantiated datasource plugins.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface[]|null
   *
   * @see getDatasources()
   */
  protected $datasourcePlugins;

  /**
   * The tracker plugin ID.
   *
   * @var string
   */
  protected $tracker = 'default';

  /**
   * The tracker plugin configuration.
   *
   * @var array
   */
  protected $tracker_config = array();

  /**
   * The tracker plugin instance.
   *
   * @var \Drupal\search_api\Tracker\TrackerInterface|null
   *
   * @see getTracker()
   */
  protected $trackerPlugin;

  /**
   * The ID of the server on which data should be indexed.
   *
   * @var string
   */
  protected $server;

  /**
   * The server entity belonging to this index.
   *
   * @var \Drupal\search_api\ServerInterface
   *
   * @see getServer()
   */
  protected $serverInstance;

  /**
   * Cached return values for several of the class's methods.
   *
   * @var array
   */
  protected $cache = array();

  /**
   * The array of processor settings.
   *
   * @var array
   *   An array containing processor settings.
   */
  protected $processors = array();

  /**
   * Cached information about the processors available for this index.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface[]|null
   *
   * @see loadProcessors()
   */
  protected $processorPlugins;

  /**
   * Whether reindexing has been triggered for this index in this page request.
   *
   * @var bool
   */
  protected $hasReindexed = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    // Merge in default options.
    // @todo Use a dedicated method, like defaultConfiguration() for plugins?
    //   And/or, better still, do this in postCreate() (and preSave()?) and not
    //   on every load.
    $this->options += array(
      'cron_limit' => \Drupal::config('search_api.settings')->get('default_cron_limit'),
      'index_directly' => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheId($sub_id) {
    return 'search_api_index:' . $this->id() . ':' . $sub_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    return isset($this->options[$name]) ? $this->options[$name] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $option) {
    $this->options[$name] = $option;
    // If the fields are changed, reset the static fields cache.
    if ($name == 'fields') {
      $this->cache = array();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasourceIds() {
    return $this->datasources;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidDatasource($datasource_id) {
    $datasources = $this->getDatasources();
    return !empty($datasources[$datasource_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource($datasource_id) {
    $datasources = $this->getDatasources();
    if (empty($datasources[$datasource_id])) {
      $args['@datasource'] = $datasource_id;
      $args['%index'] = $this->label();
      throw new SearchApiException(new FormattableMarkup('The datasource with ID "@datasource" could not be retrieved for index %index.', $args));
    }
    return $datasources[$datasource_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasources($only_enabled = TRUE) {
    if (!isset($this->datasourcePlugins)) {
      $this->datasourcePlugins = array();
      /** @var $datasource_plugin_manager \Drupal\search_api\Datasource\DatasourcePluginManager */
      $datasource_plugin_manager = \Drupal::service('plugin.manager.search_api.datasource');

      foreach ($datasource_plugin_manager->getDefinitions() as $name => $datasource_definition) {
        if (class_exists($datasource_definition['class']) && empty($this->datasourcePlugins[$name])) {
          // Create our settings for this datasource.
          $config = isset($this->datasource_configs[$name]) ? $this->datasource_configs[$name] : array();
          $config += array('index' => $this);

          /** @var $datasource \Drupal\search_api\Datasource\DatasourceInterface */
          $datasource = $datasource_plugin_manager->createInstance($name, $config);
          $this->datasourcePlugins[$name] = $datasource;
        }
        elseif (!class_exists($datasource_definition['class'])) {
          \Drupal::logger('search_api')->warning('Datasource @id specifies a non-existing @class.', array('@id' => $name, '@class' => $datasource_definition['class']));
        }
      }
    }

    // Filter datasources by status if required.
    if (!$only_enabled) {
      return $this->datasourcePlugins;
    }
    return array_intersect_key($this->datasourcePlugins, array_flip($this->datasources));
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidTracker() {
    return (bool) \Drupal::service('plugin.manager.search_api.tracker')->getDefinition($this->getTrackerId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackerId() {
    return $this->tracker;
  }

  /**
   * {@inheritdoc}
   */
  public function getTracker() {
    if (!$this->trackerPlugin) {
      $tracker_plugin_configuration = array('index' => $this) + $this->tracker_config;
      if (!($this->trackerPlugin = \Drupal::service('plugin.manager.search_api.tracker')->createInstance($this->getTrackerId(), $tracker_plugin_configuration))) {
        $args['@tracker'] = $this->tracker;
        $args['%index'] = $this->label();
        throw new SearchApiException(new FormattableMarkup('The tracker with ID "@tracker" could not be retrieved for index %index.', $args));
      }
    }

    return $this->trackerPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidServer() {
    return $this->server !== NULL && Server::load($this->server) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isServerEnabled() {
    return $this->hasValidServer() && $this->getServer()->status();
  }

  /**
   * {@inheritdoc}
   */
  public function getServerId() {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    if (!$this->serverInstance && $this->server) {
      $this->serverInstance = Server::load($this->server);
      if (!$this->serverInstance) {
        $args['@server'] = $this->server;
        $args['%index'] = $this->label();
        throw new SearchApiException(new FormattableMarkup('The server with ID "@server" could not be retrieved for index %index.', $args));
      }
    }

    return $this->serverInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server = NULL) {
    $this->serverInstance = $server;
    $this->server = $server ? $server->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessors($only_enabled = TRUE) {
    $processors = $this->loadProcessors();

    // Filter processors by status if required. Enabled processors are those
    // which have settings in the "processors" option.
    if ($only_enabled) {
      $processors_settings = $this->getProcessorSettings();
      $processors = array_intersect_key($processors, $processors_settings);
    }

    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessorsByStage($stage, $only_enabled = TRUE) {
    $processors = $this->loadProcessors();
    $processor_settings = $this->getProcessorSettings();
    $processor_weights = array();

    // Get a list of all processors meeting the criteria (stage and, optionally,
    // enabled) along with their effective weights (user-set or default).
    foreach ($processors as $name => $processor) {
      if ($processor->supportsStage($stage) && !($only_enabled && empty($processor_settings[$name]))) {
        if (!empty($processor_settings[$name]['weights'][$stage])) {
          $processor_weights[$name] = $processor_settings[$name]['weights'][$stage];
        }
        else {
          $processor_weights[$name] = $processor->getDefaultWeight($stage);
        }
      }
    }

    // Sort requested processors by weight.
    asort($processor_weights);

    $return_processors = array();
    foreach ($processor_weights as $name => $weight) {
      $return_processors[$name] = $processors[$name];
    }
    return $return_processors;
  }

  /**
   * Retrieves all processors supported by this index.
   *
   * @return \Drupal\search_api\Processor\ProcessorInterface[]
   *   The loaded processors, keyed by processor ID.
   */
  protected function loadProcessors() {
    if (empty($this->processorPlugins)) {
      /** @var $processor_plugin_manager \Drupal\search_api\Processor\ProcessorPluginManager */
      $processor_plugin_manager = \Drupal::service('plugin.manager.search_api.processor');
      $processor_settings = $this->getProcessorSettings();

      foreach ($processor_plugin_manager->getDefinitions() as $name => $processor_definition) {
        if (class_exists($processor_definition['class']) && empty($this->processorPlugins[$name])) {
          // Create our settings for this processor.
          $settings = empty($processor_settings[$name]['settings']) ? array() : $processor_settings[$name]['settings'];
          $settings['index'] = $this;

          /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
          $processor = $processor_plugin_manager->createInstance($name, $settings);
          if ($processor->supportsIndex($this)) {
            $this->processorPlugins[$name] = $processor;
          }
        }
        elseif (!class_exists($processor_definition['class'])) {
          \Drupal::logger('search_api')->warning('Processor @id specifies a non-existing @class.', array('@id' => $name, '@class' => $processor_definition['class']));
        }
      }
    }

    return $this->processorPlugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessorSettings() {
    return $this->processors;
  }

  /**
   * {@inheritdoc}
   */
  public function setProcessorSettings(array $processors) {
    $this->processors = $processors;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($this->getProcessorsByStage(ProcessorInterface::STAGE_PREPROCESS_INDEX) as $processor) {
      $processor->preprocessIndexItems($items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    foreach ($this->getProcessorsByStage(ProcessorInterface::STAGE_PREPROCESS_QUERY) as $processor) {
      $processor->preprocessSearchQuery($query);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
    foreach (array_reverse($this->getProcessorsByStage(ProcessorInterface::STAGE_POSTPROCESS_QUERY)) as $processor) {
      $processor->postprocessSearchResults($results);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addField(FieldInterface $field) {
    $field_id = $field->getFieldIdentifier();
    if (Utility::isFieldIdReserved($field_id)) {
      $args['%field_id'] = $field_id;
      throw new SearchApiException(new FormattableMarkup('%field_id is a reserved value and cannot be used as the machine name of a normal field.', $args));
    }

    // @todo If we have a single field object per field on the index, we'd just
    //   need the first two checks – and that would also (correctly) prevent
    //   renaming a field to an existing, different machine name.
    $old_field = $this->getField($field_id);
    $would_overwrite = $old_field
      && $old_field != $field
      && ($old_field->getDatasourceId() != $field->getDatasourceId()
        || $old_field->getPropertyPath() != $field->getPropertyPath());
    if ($would_overwrite) {
      $args['%field_id'] = $field_id;
      throw new SearchApiException(new FormattableMarkup('Cannot add field with machine name %field_id: machine name is already taken.', $args));
    }

    $this->fields[$field_id] = $field->getSettings();

    $this->resetCaches();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function renameField($old_field_id, $new_field_id) {
    if (isset($this->fields[$old_field_id])) {
      $args['%field_id'] = $old_field_id;
      throw new SearchApiException(new FormattableMarkup('Could not rename field with machine name %field_id: no such field.', $args));
    }
    if (Utility::isFieldIdReserved($new_field_id)) {
      $args['%field_id'] = $new_field_id;
      throw new SearchApiException(new FormattableMarkup('%field_id is a reserved value and cannot be used as the machine name of a normal field.', $args));
    }
    if (isset($this->fields[$new_field_id])) {
      $args['%field_id'] = $new_field_id;
      throw new SearchApiException(new FormattableMarkup('%field_id is a reserved value and cannot be used as the machine name of a normal field.', $args));
    }

    $this->fields[$new_field_id] = $this->fields[$old_field_id];
    unset($this->fields[$old_field_id]);

    $this->resetCaches();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeField($field_id) {
    $field = $this->getField($field_id);
    if (!$field) {
      return $this;
    }
    if ($field->isIndexedLocked()) {
      $args['%field_id'] = $field_id;
      throw new SearchApiException(new FormattableMarkup('Cannot remove field with machine name %field_id: field is locked.', $args));
    }

    unset($this->fields[$field_id]);

    $this->resetCaches();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    $fields = $this->getCache(__FUNCTION__, FALSE);
    if (!$fields) {
      $fields = array();
      foreach ($this->getFieldSettings() as $key => $field_info) {
        $fields[$key] = Utility::createField($this, $key, $field_info);
      }
    }
    $this->setCache(__FUNCTION__, $fields, FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getField($field_id) {
    $fields = $this->getFields();
    return isset($fields[$field_id]) ? $fields[$field_id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsByDatasource($datasource_id) {
    $datasource_fields = $this->getCache(__FUNCTION__);
    if (!$datasource_fields) {
      $datasource_fields = array_fill_keys($this->datasources, array());
      $datasource_fields[NULL] = array();
      foreach ($this->getFields() as $field_id => $field) {
        $datasource_fields[$field->getDatasourceId()][$field_id] = $field;
      }
      $this->setCache(__FUNCTION__, $datasource_fields);
    }

    return $datasource_fields[$datasource_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFulltextFields() {
    $fulltext_fields = $this->getCache(__FUNCTION__);
    if (!$fulltext_fields) {
      $fulltext_fields = array();
      foreach ($this->getFieldSettings() as $key => $field_info) {
        if (Utility::isTextType($field_info['type'])) {
          $fulltext_fields[] = $key;
        }
      }
      $this->setCache(__FUNCTION__, $fulltext_fields);
    }
    return $fulltext_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldSettings() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldSettings(array $fields = array()) {
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions($datasource_id, $alter = TRUE) {
    $alter = $alter ? 1 : 0;
    $properties = $this->getCache(__FUNCTION__);
    if (!isset($properties[$datasource_id][$alter])) {
      if (isset($datasource_id)) {
        $datasource = $this->getDatasource($datasource_id);
        $properties[$datasource_id][$alter] = $datasource->getPropertyDefinitions();
      }
      else {
        $datasource = NULL;
        $properties[$datasource_id][$alter] = array();
      }
      if ($alter) {
        foreach ($this->getProcessors() as $processor) {
          $processor->alterPropertyDefinitions($properties[$datasource_id][$alter], $datasource);
        }
      }
      $this->setCache(__FUNCTION__, $properties);
    }
    return $properties[$datasource_id][$alter];
  }

  /**
   * {@inheritdoc}
   */
  public function loadItem($item_id) {
    $items = $this->loadItemsMultiple(array($item_id));
    return $items ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadItemsMultiple(array $item_ids, $group_by_datasource = FALSE) {
    $items_by_datasource = array();
    foreach ($item_ids as $item_id) {
      list($datasource_id, $raw_id) = Utility::splitCombinedId($item_id);
      $items_by_datasource[$datasource_id][$item_id] = $raw_id;
    }
    $items = array();
    foreach ($items_by_datasource as $datasource_id => $raw_ids) {
      try {
        foreach ($this->getDatasource($datasource_id)->loadMultiple($raw_ids) as $raw_id => $item) {
          $id = Utility::createCombinedId($datasource_id, $raw_id);
          if ($group_by_datasource) {
            $items[$datasource_id][$id] = $item;
          }
          else {
            $items[$id] = $item;
          }
        }
      }
      catch (SearchApiException $e) {
        watchdog_exception('search_api', $e);
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems($limit = '-1', $datasource_id = NULL) {
    if ($this->hasValidTracker() && !$this->isReadOnly()) {
      $tracker = $this->getTracker();
      $next_set = $tracker->getRemainingItems($limit, $datasource_id);
      $items = $this->loadItemsMultiple($next_set);
      if ($items) {
        try {
          return count($this->indexSpecificItems($items));
        }
        catch (SearchApiException $e) {
          $variables['%index'] = $this->label();
          watchdog_exception('search_api', $e, '%type while trying to index items on index %index: @message in %function (line %line of %file)', $variables);
        }
      }
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function indexSpecificItems(array $search_objects) {
    if (!$search_objects || $this->read_only) {
      return array();
    }
    if (!$this->status) {
      throw new SearchApiException(new FormattableMarkup("Couldn't index values on index %index (index is disabled)", array('%index' => $this->label())));
    }
    if (empty($this->fields)) {
      throw new SearchApiException(new FormattableMarkup("Couldn't index values on index %index (no fields selected)", array('%index' => $this->label())));
    }

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    $items = array();
    foreach ($search_objects as $item_id => $object) {
      $items[$item_id] = Utility::createItemFromObject($this, $object, $item_id);
      // This will cache the extracted fields so processors, etc., can retrieve
      // them directly.
      $items[$item_id]->getFields();
    }

    // Remember the items that were initially passed, to be able to determine
    // the items rejected by alter hooks and processors afterwards.
    $rejected_ids = array_keys($items);
    $rejected_ids = array_combine($rejected_ids, $rejected_ids);

    // Preprocess the indexed items.
    \Drupal::moduleHandler()->alter('search_api_index_items', $this, $items);
    $this->preprocessIndexItems($items);

    // Remove all items still in $items from $rejected_ids. Thus, only the
    // rejected items' IDs are still contained in $ret, to later be returned
    // along with the successfully indexed ones.
    foreach ($items as $item_id => $item) {
      unset($rejected_ids[$item_id]);
    }

    // Items that are rejected should also be deleted from the server.
    if ($rejected_ids) {
      $this->getServer()->deleteItems($this, $rejected_ids);
    }

    $indexed_ids = array();
    if ($items) {
      $indexed_ids = $this->getServer()->indexItems($this, $items);
    }

    // Return the IDs of all items that were either successfully indexed or
    // rejected before being handed to the server.
    $processed_ids = array_merge(array_values($rejected_ids), array_values($indexed_ids));

    if ($processed_ids) {
      if ($this->hasValidTracker()) {
        $this->getTracker()->trackItemsIndexed($processed_ids);
      }
      // Since we've indexed items now, triggering reindexing would have some
      // effect again. Therefore, we reset the flag.
      $this->hasReindexed = FALSE;
      \Drupal::moduleHandler()->invokeAll('search_api_items_indexed', array($this, $processed_ids));
    }

    return $processed_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsInserted($datasource_id, array $ids) {
    $this->trackItemsInsertedOrUpdated($datasource_id, $ids, __FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsUpdated($datasource_id, array $ids) {
    $this->trackItemsInsertedOrUpdated($datasource_id, $ids, __FUNCTION__);
  }

  /**
   * Tracks insertion or updating of items.
   *
   * Used as a helper method in trackItemsInserted() and trackItemsUpdated() to
   * avoid code duplication.
   *
   * @param string $datasource_id
   *   The ID of the datasource to which the items belong.
   * @param array $ids
   *   An array of datasource-specific item IDs.
   * @param string $tracker_method
   *   The method to call on the tracker. Must be either "trackItemsInserted" or
   *   "trackItemsUpdated".
   */
  protected function trackItemsInsertedOrUpdated($datasource_id, array $ids, $tracker_method) {
    if ($this->hasValidTracker() && $this->status() && \Drupal::getContainer()->get('search_api.index_task_manager')->isTrackingComplete($this)) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = Utility::createCombinedId($datasource_id, $id);
      }
      $this->getTracker()->$tracker_method($item_ids);
      if (!$this->isReadOnly() && $this->getOption('index_directly')) {
        try {
          $items = $this->loadItemsMultiple($item_ids);
          if ($items) {
            $this->indexSpecificItems($items);
          }
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted($datasource_id, array $ids) {
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = Utility::createCombinedId($datasource_id, $id);
      }
      $this->getTracker()->trackItemsDeleted($item_ids);
      if (!$this->isReadOnly() && $this->isServerEnabled()) {
        $this->getServer()->deleteItems($this, $item_ids);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function reindex() {
    if ($this->status() && !$this->hasReindexed) {
      $this->hasReindexed = TRUE;
      $this->getTracker()->trackAllItemsUpdated();
      \Drupal::moduleHandler()->invokeAll('search_api_index_reindex', array($this, FALSE));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    if ($this->status()) {
      // Only invoke the hook if we actually did something.
      $invoke_hook = FALSE;
      if (!$this->hasReindexed) {
        $invoke_hook = TRUE;
        $this->hasReindexed = TRUE;
        $this->getTracker()->trackAllItemsUpdated();
      }
      if (!$this->isReadOnly()) {
        $invoke_hook = TRUE;
        $this->getServer()->deleteAllIndexItems($this);
      }
      if ($invoke_hook) {
        \Drupal::moduleHandler()->invokeAll('search_api_index_reindex', array($this, !$this->isReadOnly()));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isReindexing() {
    return $this->hasReindexed;
  }

  /**
   * Retrieves data from the static and/or stored cache.
   *
   * @param string $method
   *   The name of the method for which data is requested. Used to construct the
   *   cache ID.
   * @param bool $static_only
   *   (optional) If TRUE, only consult the static cache for this page request,
   *   don't attempt to load the value from the stored cache.
   *
   * @return mixed|null
   *   The cached data, or NULL if it could not be found.
   */
  protected function getCache($method, $static_only = TRUE) {
    if (isset($this->cache[$method])) {
      return $this->cache[$method];
    }

    if (!$static_only) {
      $cid = $this->getCacheId($method);
      if ($cached = \Drupal::cache()->get($cid)) {
        if (is_array($cached->data)) {
          $this->updateFieldsIndex($cached->data);
        }
        $this->cache[$method] = $cached->data;
        return $this->cache[$method];
      }
    }

    return NULL;
  }

  /**
   * Sets data in the static and/or stored cache.
   *
   * @param string $method
   *   The name of the method for which cached data should be set. Used to
   *   construct the cache ID.
   * @param mixed $value
   *   The value to set for the cache.
   * @param bool $static_only
   *   (optional) If TRUE, only set the value in the static cache for this page
   *   request, not in the stored cache.
   *
   * @return $this
   */
  protected function setCache($method, $value, $static_only = TRUE) {
    $this->cache[$method] = $value;
    if (!$static_only) {
      $cid = $this->getCacheId($method);
      \Drupal::cache()
        ->set($cid, $value, Cache::PERMANENT, $this->getCacheTags());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCaches($include_stored = TRUE) {
    $this->datasourcePlugins = NULL;
    $this->trackerPlugin = NULL;
    $this->serverInstance = NULL;
    $this->processorPlugins = NULL;
    $this->cache = array();
    if ($include_stored) {
      Cache::invalidateTags($this->getCacheTags());
    }
  }

  /**
   * Sets this object as the index for all fields contained in the given array.
   *
   * This is important when loading fields from the cache, because their index
   * objects might point to another instance of this index.
   *
   * @param array $fields
   *   An array containing various values, some of which might be
   *   \Drupal\search_api\Item\FieldInterface objects and some of which might be
   *   nested arrays containing such objects.
   */
  protected function updateFieldsIndex(array $fields) {
    foreach ($fields as $value) {
      if (is_array($value)) {
        $this->updateFieldsIndex($value);
      }
      elseif ($value instanceof FieldInterface) {
        $value->setIndex($this);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $options = array()) {
    if (!$this->status()) {
      throw new SearchApiException('Cannot search on a disabled index.');
    }
    return Utility::createQuery($this, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Prevent enabling of indexes when the server is disabled.
    if ($this->status() && !$this->isServerEnabled()) {
      $this->disable();
    }

    // Remove all "locked" and "hidden" flags from all fields of the index. If
    // they are still valid, they should be re-added by the processors.
    foreach ($this->fields as $field_id => $field_settings) {
      unset($this->fields[$field_id]['indexed_locked']);
      unset($this->fields[$field_id]['type_locked']);
      unset($this->fields[$field_id]['hidden']);
    }

    // We first have to check for locked processors, otherwise their
    // preIndexSave() methods might not be called in the next step.
    foreach ($this->getProcessors(FALSE) as $processor_id => $processor) {
      if ($processor->isLocked() && !isset($this->processors[$processor_id])) {
        $this->processors[$processor_id] = array(
          'processor_id' => $processor_id,
          'weights' => array(),
          'settings' => array(),
        );
      }
    }

    // Reset the cache so the used processors and fields will be up-to-date.
    $this->resetCaches();

    // Call the preIndexSave() method of all applicable processors.
    foreach ($this->getProcessorsByStage(ProcessorInterface::STAGE_PRE_INDEX_SAVE) as $processor) {
      $processor->preIndexSave();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $this->resetCaches();

    try {
      // Fake an original for inserts to make code cleaner.
      $original = $update ? $this->original : static::create(array('status' => FALSE));

      if ($this->status() && $original->status()) {
        // React on possible changes that would require re-indexing, etc.
        $this->reactToServerSwitch($original);
        $this->reactToDatasourceSwitch($original);
        $this->reactToTrackerSwitch($original);
        $this->reactToProcessorChanges($original);
      }
      elseif (!$this->status() && $original->status()) {
        if ($this->hasValidTracker()) {
          \Drupal::getContainer()->get('search_api.index_task_manager')->stopTracking($this);
        }
        if ($original->isServerEnabled()) {
          $original->getServer()->removeIndex($this);
        }
      }
      elseif ($this->status() && !$original->status()) {
        $this->getServer()->addIndex($this);
        if ($this->hasValidTracker()) {
          \Drupal::getContainer()->get('search_api.index_task_manager')->startTracking($this);
        }
      }

      $index_task_manager = \Drupal::getContainer()->get('search_api.index_task_manager');
      if (!$index_task_manager->isTrackingComplete($this)) {
        // Give tests and site admins the possibility to disable the use of a
        // batch for tracking items. Also, do not use a batch if running in the
        // CLI.
        $use_batch = \Drupal::state()->get('search_api_use_tracking_batch', TRUE);
        if (!$use_batch || php_sapi_name() == 'cli') {
          $index_task_manager->addItemsAll($this);
        }
        else {
          $index_task_manager->addItemsBatch($this);
        }
      }

      if (\Drupal::moduleHandler()->moduleExists('views')) {
        Views::viewsData()->clear();
        // Remove this line when https://www.drupal.org/node/2370365 gets fixed.
        Cache::invalidateTags(array('extension:views'));
        \Drupal::cache('discovery')->delete('views:wizard');
      }

      $this->resetCaches();
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
    }
  }

  /**
   * Checks whether the index switched server and reacts accordingly.
   *
   * Used as a helper method in postSave(). Should only be called when the index
   * was enabled before the change and remained so.
   *
   * @param \Drupal\search_api\IndexInterface $original
   *   The previous version of the index.
   */
  protected function reactToServerSwitch(IndexInterface $original) {
    if ($this->getServerId() != $original->getServerId()) {
      if ($original->isServerEnabled()) {
        $original->getServer()->removeIndex($this);
      }
      if ($this->isServerEnabled()) {
        $this->getServer()->addIndex($this);
      }
      // When the server changes we also need to trigger a reindex.
      $this->reindex();
    }
    elseif ($this->isServerEnabled()) {
      // Tell the server the index configuration got updated
      $this->getServer()->updateIndex($this);
    }
  }

  /**
   * Checks whether the index's datasources changed and reacts accordingly.
   *
   * Used as a helper method in postSave(). Should only be called when the index
   * was enabled before the change and remained so.
   *
   * @param \Drupal\search_api\IndexInterface $original
   *   The previous version of the index.
   */
  protected function reactToDatasourceSwitch(IndexInterface $original) {
    $new_datasource_ids = $this->getDatasourceIds();
    $original_datasource_ids = $original->getDatasourceIds();
    if ($new_datasource_ids != $original_datasource_ids) {
      $added = array_diff($new_datasource_ids, $original_datasource_ids);
      $removed = array_diff($original_datasource_ids, $new_datasource_ids);
      $index_task_manager = \Drupal::getContainer()->get('search_api.index_task_manager');
      $index_task_manager->stopTracking($this, $removed);
      $index_task_manager->startTracking($this, $added);
    }
  }


  /**
   * Checks whether the index switched tracker plugin and reacts accordingly.
   *
   * Used as a helper method in postSave(). Should only be called when the index
   * was enabled before the change and remained so.
   *
   * @param \Drupal\search_api\IndexInterface $original
   *   The previous version of the index.
   */
  protected function reactToTrackerSwitch(IndexInterface $original) {
    if ($this->tracker != $original->getTrackerId()) {
      $index_task_manager = \Drupal::getContainer()->get('search_api.index_task_manager');
      if ($original->hasValidTracker()) {
        $index_task_manager->stopTracking($this);
      }
      if ($this->hasValidTracker()) {
        $index_task_manager->startTracking($this);
      }
    }
  }

  /**
   * Reacts to changes in processor configuration.
   *
   * @param \Drupal\search_api\IndexInterface $original
   *   The previous version of the index.
   */
  protected function reactToProcessorChanges(IndexInterface $original) {
    $original_settings = $original->getProcessorSettings();
    $new_settings = $this->getProcessorSettings();

    // Only actually do something when the processor settings are changed.
    if ($original_settings != $new_settings) {
      $requires_reindex = FALSE;
      $processors = $this->getProcessors(FALSE);

      // Loop over all new settings and check if the processors were already set
      // in the original entity.
      foreach ($new_settings as $key => $setting) {
        // The processor is new, because it wasn't configured in the original
        // entity.
        if (!isset($original_settings[$key])) {
          if ($processors[$key]->requiresReindexing(NULL, $new_settings[$key])) {
            $requires_reindex = TRUE;
            break;
          }
        }
      }

      if (!$requires_reindex) {
        // Loop over all original settings and check if one of them has been
        // removed or changed.
        foreach ($original_settings as $key => $old_processor_settings) {
          // If the processor isn't even available any more, we can't determine
          // what it would have said about the need to reindex. Err on the side
          // of caution and guess "yes".
          if (empty($processors[$key])) {
            $requires_reindex = TRUE;
            break;
          }
          $new_processor_settings = isset($new_settings[$key]) ? $new_settings[$key] : NULL;
          if (!isset($new_processor_settings) || $new_processor_settings != $old_processor_settings) {
            if ($processors[$key]->requiresReindexing($old_processor_settings, $new_processor_settings)) {
              $requires_reindex = TRUE;
              break;
            }
          }

        }
      }

      if ($requires_reindex) {
        $this->reindex();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    /** @var \Drupal\search_api\IndexInterface[] $entities */
    foreach ($entities as $index) {
      if ($index->hasValidTracker()) {
        $index->getTracker()->trackAllItemsDeleted();
      }
      if ($index->hasValidServer()) {
        $index->getServer()->removeIndex($index);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    if (\Drupal::moduleHandler()->moduleExists('views')) {
      Views::viewsData()->clear();
      // Remove this line when https://www.drupal.org/node/2370365 gets fixed.
      Cache::invalidateTags(array('extension:views'));
      \Drupal::cache('discovery')->delete('views:wizard');
    }

    /** @var \Drupal\user\SharedTempStore $temp_store */
    $temp_store = \Drupal::service('user.shared_tempstore')->get('search_api_index');
    foreach ($entities as $entity) {
      try {
        $temp_store->delete($entity->id());
      }
      catch (TempStoreException $e) {
        // Can't really be helped, I guess. But is also very unlikely to happen.
        // Ignore it.
      }
    }
  }

  // @todo Override static load() etc. methods? Measure performance difference.

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = $this->getDependencyData();
    $this->dependencies = array();

    foreach ($dependencies as $type => $list) {
      $this->dependencies[$type] = array_keys($list);
    }

    return $this;
  }

  /**
   * Retrieves data about this index's dependencies.
   *
   * The return value is structured as follows:
   *
   * @code
   * array(
   *   'config' => array(
   *     'CONFIG_DEPENDENCY_KEY' => array(
   *       'always' => array(
   *         'processors' => array(
   *           'PROCESSOR_ID' => $processor,
   *         ),
   *         'datasources' => array(
   *           'DATASOURCE_ID_1' => $datasource_1,
   *           'DATASOURCE_ID_2' => $datasource_2,
   *         ),
   *       ),
   *       'optional' => array(
   *         'index' => array(
   *           'INDEX_ID' => $index,
   *         ),
   *         'tracker' => array(
   *           'TRACKER_ID' => $tracker,
   *         ),
   *       ),
   *     ),
   *   )
   * )
   * @endcode
   *
   * @return object[][][][][]
   *   An associative array containing the index's dependencies. The array is
   *   first keyed by the config dependency type ("module", "config", etc.) and
   *   then by the names of the config dependencies of that type which the index
   *   has. The values are associative arrays with up to two keys, "always" and
   *   "optional", specifying whether the dependency is a hard one by the plugin
   *   (or index) in question or potentially depending on the configuration. The
   *   values on this level are arrays with keys "index", "tracker",
   *   "datasources" and/or "processors" and values arrays of IDs mapped to
   *   their entities/plugins.
   */
  protected function getDependencyData() {
    $dependency_data = array();

    // Since calculateDependencies() will work directly on the $dependencies
    // property, we first save its original state and then restore it
    // afterwards.
    $original_dependencies = $this->dependencies;
    parent::calculateDependencies();
    foreach ($this->dependencies as $dependency_type => $list) {
      foreach ($list as $name) {
        $dependency_data[$dependency_type][$name]['always']['index'][$this->id] = $this;
      }
    }
    $this->dependencies = $original_dependencies;

    // The server needs special treatment, since it is a dependency of the index
    // itself, and not one of its plugins.
    if ($this->hasValidServer()) {
      $name = $this->getServer()->getConfigDependencyName();
      $dependency_data['config'][$name]['optional']['index'][$this->id] = $this;
    }

    // All other plugins can be treated uniformly.
    $plugins = $this->getAllPlugins();

    foreach ($plugins as $plugin_type => $type_plugins) {
      foreach ($type_plugins as $plugin_id => $plugin) {
        // Largely copied from
        // \Drupal\Core\Plugin\PluginDependencyTrait::calculatePluginDependencies().
        $definition = $plugin->getPluginDefinition();

        // First, always depend on the module providing the plugin.
        $dependency_data['module'][$definition['provider']]['always'][$plugin_type][$plugin_id] = $plugin;

        // Plugins can declare additional dependencies in their definition.
        if (isset($definition['config_dependencies'])) {
          foreach ($definition['config_dependencies'] as $dependency_type => $list) {
            foreach ($list as $name) {
              $dependency_data[$dependency_type][$name]['always'][$plugin_type][$plugin_id] = $plugin;
            }
          }
        }

        // Finally, add the dynamically-calculated dependencies of the plugin.
        foreach ($plugin->calculateDependencies() as $dependency_type => $list) {
          foreach ($list as $name) {
            $dependency_data[$dependency_type][$name]['optional'][$plugin_type][$plugin_id] = $plugin;
          }
        }
      }
    }

    return $dependency_data;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    $all_plugins = $this->getAllPlugins();
    $dependency_data = $this->getDependencyData();
    // Make sure our dependency data has the exact same keys as $dependencies,
    // to simplify the subsequent code.
    $dependencies = array_filter($dependencies);
    $dependency_data = array_intersect_key($dependency_data, $dependencies);
    $dependency_data += array_fill_keys(array_keys($dependencies), array());
    $call_on_removal = array();

    foreach ($dependencies as $dependency_type => $dependency_objects) {
      // Annoyingly, modules and theme dependencies come not keyed by dependency
      // name here, while entities do. Flip the array for modules and themes to
      // make the code simpler.
      if (in_array($dependency_type, array('module', 'theme'))) {
        $dependency_objects = array_flip($dependency_objects);
      }
      $dependency_data[$dependency_type] = array_intersect_key($dependency_data[$dependency_type], $dependency_objects);
      foreach ($dependency_data[$dependency_type] as $name => $dependency_sources) {
        // We first remove all the "hard" dependencies.
        if (!empty($dependency_sources['always'])) {
          foreach ($dependency_sources['always'] as $plugin_type => $plugins) {
            // We can hardly remove the index itself.
            if ($plugin_type == 'index') {
              continue;
            }

            $all_plugins[$plugin_type] = array_diff_key($all_plugins[$plugin_type], $plugins);
            $changed = TRUE;
          }
        }

        // Then, collect all the optional ones.
        if (!empty($dependency_sources['optional'])) {
          // However this plays out, it will lead to a change.
          $changed = TRUE;

          foreach ($dependency_sources['optional'] as $plugin_type => $plugins) {
            // Deal with the index right away, since that dependency can only be
            // the server.
            if ($plugin_type == 'index') {
              $this->setServer(NULL);
              continue;
            }

            // Only include those plugins that have not already been removed.
            $plugins = array_intersect_key($plugins, $all_plugins[$plugin_type]);

            foreach ($plugins as $plugin_id => $plugin) {
              $call_on_removal[$plugin_type][$plugin_id][$dependency_type][$name] = $dependency_objects[$name];
            }
          }
        }
      }
    }

    // Now for all plugins with optional dependencies (stored in
    // $call_on_removal, mapped to their removed dependencies) call their
    // onDependencyRemoval() methods.
    $updated_config = array();
    foreach ($call_on_removal as $plugin_type => $plugins) {
      foreach ($plugins as $plugin_id => $plugin_dependencies) {
        $removal_successful = $all_plugins[$plugin_type][$plugin_id]->onDependencyRemoval($plugin_dependencies);
        // If the plugin was successfully changed to remove the dependency,
        // remember the new configuration to later set it. Otherwise, remove the
        // plugin from the index so the dependency still gets removed.
        if ($removal_successful) {
          $updated_config[$plugin_type][$plugin_id] = $all_plugins[$plugin_type][$plugin_id]->getConfiguration();
        }
        else {
          unset($all_plugins[$plugin_type][$plugin_id]);
        }
      }
    }

    // The handling of how we translate plugin changes back to the index varies
    // according to plugin type, unfortunately.
    // First, remove plugins that need to be removed.
    $this->processors = array_intersect_key($this->processors, $all_plugins['processors']);
    $this->datasources = array_keys($all_plugins['datasources']);
    $this->datasource_configs = array_intersect_key($this->datasource_configs, $all_plugins['datasources']);
    // There always needs to be a tracker.
    if (empty($all_plugins['tracker'])) {
      $this->tracker = \Drupal::config('search_api.settings')->get('default_tracker');
      $this->tracker_config = array();
    }
    // There also always needs to be a datasource, but here we have no easy way
    // out – if we had to remove all datasources, the operation fails. Return
    // FALSE to indicate this, which will cause the index to be deleted.
    if (!$this->datasources) {
      return FALSE;
    }

    // Then, update configuration as necessary.
    foreach ($updated_config as $plugin_type => $plugin_configs) {
      foreach ($plugin_configs as $plugin_id => $plugin_config) {
        switch ($plugin_type) {
          case 'processors':
            $this->processors[$plugin_id]['settings'] = $plugin_config;
            break;
          case 'datasources':
            $this->datasource_configs[$plugin_id] = $plugin_config;
            break;
          case 'tracker':
            $this->tracker_config = $plugin_config;
            break;
        }
      }
    }

    if ($changed) {
      $this->resetCaches();
    }

    return $changed;
  }

  /**
   * Retrieves all the plugins contained in this index.
   *
   * @return \Drupal\search_api\Plugin\IndexPluginInterface[][]
   *   All plugins contained in this index, keyed by their property on the index
   *   and their plugin ID.
   */
  protected function getAllPlugins() {
    $plugins = array();

    if ($this->hasValidTracker()) {
      $plugins['tracker'][$this->getTrackerId()] = $this->getTracker();
    }
    $plugins['processors'] = $this->getProcessors();
    $plugins['datasources'] = $this->getDatasources();

    return $plugins;
  }

  /**
   * Implements the magic __clone() method.
   *
   * Prevents the cached plugins and fields from being cloned, too (since they
   * would then point to the wrong index object).
   */
  public function __clone() {
    $this->resetCaches(FALSE);
  }

  /**
   * Implements the magic __sleep() method.
   *
   * Prevents the cached plugins and fields from being serialized.
   */
  public function __sleep() {
    $properties = get_object_vars($this);
    unset($properties['datasourcePlugins']);
    unset($properties['trackerPlugin']);
    unset($properties['serverInstance']);
    unset($properties['processorPlugins']);
    unset($properties['cache']);
    return array_keys($properties);
  }

}
