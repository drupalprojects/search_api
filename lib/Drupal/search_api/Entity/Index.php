<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   controllers = {
 *     "storage" = "Drupal\search_api\IndexStorage",
 *     "access" = "Drupal\search_api\Handler\IndexAccessHandler",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\IndexForm",
 *       "edit" = "Drupal\search_api\Form\IndexForm",
 *       "fields" = "Drupal\search_api\Form\IndexFieldsForm",
 *       "filters" = "Drupal\search_api\Form\IndexFiltersForm",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm",
 *     },
 *   },
 *   config_prefix = "index",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "search_api.index_view",
 *     "add-form" = "search_api.index_add",
 *     "edit-form" = "search_api.index_edit",
 *     "delete-form" = "search_api.index_delete",
 *     "disable" = "search_api.index_disable",
 *     "enable" = "search_api.index_enable",
 *   }
 * )
 */
class Index extends ConfigEntityBase implements IndexInterface {

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
   * A flag indicating whether to write to this index.
   *
   * @var integer
   */
  public $readOnly = FALSE;

  /**
   * An array of options for configuring this index. The layout is as follows:
   * - cron_limit: The maximum number of items to be indexed per cron batch.
   * - index_directly: Boolean setting whether entities are indexed immediately
   *   after they are created or updated.
   * - fields: An array of all indexed fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_data_types().
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
   * - additional fields: An associative array with keys and values being the
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
  public $options = array(
    'cron_limit' => SEARCH_API_DEFAULT_CRON_LIMIT,
    'index_directly' => FALSE,
  );

  /**
   * The datasource plugin ID.
   *
   * @var string
   */
  public $datasourcePluginId;

  /**
   * The datasource plugin configuration.
   *
   * @var array
   */
  public $datasourcePluginConfig = array();

  /**
   * The datasource plugin instance.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasourcePluginInstance;

  /**
   * The machine name of the server on which data should be indexed.
   *
   * @var string
   */
  public $serverMachineName;

  /**
   * The server object instance.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * Cached fields data for getFields().
   *
   * @var array
   */
  protected $fields;

  /**
   * Cached fulltext fields data for getFulltextFields().
   *
   * @var array
   */
  protected $fulltextFields;

  /**
   * Cached fulltext fields data for getProcessors().
   *
   * @var array
   */
  protected $processors;

  /**
   * Clones an index object.
   */
  public function __clone() {
    // Prevent the datasource and server instance from being cloned.
    $this->datasourcePluginInstance = $this->server = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->machine_name;
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
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->readOnly;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    // Get the options.
    $options = $this->getOptions();
    // Get the option value for the given key.
    return isset($options[$name]) ? $options[$name] : $default;
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
  public function setOptions($options) {
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $option) {
    $this->options[$name] = $option;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidDatasource() {
    // Get the datasource plugin definition.
    $datasource_plugin_definition = \Drupal::service('search_api.datasource.plugin.manager')->getDefinition($this->datasourcePluginId);
    // Determine whether the datasource is valid.
    return !empty($datasource_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasourceId() {
    return $this->datasourcePluginId;
  }

    /**
     * {@inheritdoc}
     */
    public function getDatasource() {
    // Check if the datasource plugin instance needs to be resolved.
    if (!$this->datasourcePluginInstance && $this->hasValidDatasource()) {
      // Get the ID of the datasource plugin.
      $datasource_plugin_id = $this->datasourcePluginId;
      // Get the datasource plugin manager.
      $datasource_plugin_manager = \Drupal::service('search_api.datasource.plugin.manager');
      // Get the plugin configuration for the datasource.
      $datasource_plugin_configuration = array('index' => $this) + $this->datasourcePluginConfig;
      // Create a datasource plugin instance.
      $this->datasourcePluginInstance = $datasource_plugin_manager->createInstance($datasource_plugin_id, $datasource_plugin_configuration);
    }
    return $this->datasourcePluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidServer() {
    return $this->serverMachineName !== NULL && \Drupal::entityManager()->getStorage('search_api_server')->load($this->serverMachineName) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getServerId() {
    return $this->serverMachineName;
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    // Check if the server needs to be resolved. Note we do not use
    // hasValidServer to prevent duplicate load calls to the storage controller.
    if (!$this->server) {
      // Get the server machine name.
      $server_machine_name = $this->serverMachineName;
      // Get the server from the storage.
      $this->server = \Drupal::entityManager()->getStorage('search_api_server')->load($server_machine_name);
    }
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server = NULL) {
    // Overwrite the current server instance.
    $this->server = $server;
    // Overwrite the server machine name.
    $this->serverMachineName = $server ? $server->id() : NULL;
  }

  /**
   * Indexes items on this index.
   *
   * Will return an array of IDs of items that should be marked as indexed –
   * i.e., items that were either rejected by a data-alter callback or were
   * successfully indexed.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface[] $items
   *   An array of items to index, of this index's item type.
   *
   * @return array
   *   An array of the IDs of all items that should be marked as indexed.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred during indexing.
   */
  public function indexItems(array $items) {
    if (!$items || $this->readOnly) {
      return array();
    }
    if (!$this->status) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (index is disabled)", array('@name' => $this->name)));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }

    $fields = $this->options['fields'];
    //$custom_type_fields = array();
    foreach ($fields as $field => $info) {
      if (isset($info['real_type'])) {
        $custom_type = $info['real_type'];
        if ($this->getServer()->supportsDatatype($custom_type)) {
          $fields[$field]['type'] = $info['real_type'];
        }
        //$custom_type_fields[$custom_type][] = $field;
      }
    }
    if (empty($fields)) {
      throw new SearchApiException(t("Couldn't index values on '@name' index (no fields selected)", array('@name' => $this->name)));
    }

    $extracted_items = array();
    foreach ($items as $id => $item) {
      $extracted_items[$id] = $fields;
      search_api_extract_fields($item, $extracted_items[$id]);
      // @todo Custom data type conversion logic.
    }

    // Remember the item IDs we got passed.
    $ret = array_keys($extracted_items);

    // Preprocess the indexed items.
    \Drupal::moduleHandler()->alter('search_api_index_items', $extracted_items, $this);
    $this->preprocessIndexItems($extracted_items);

    // Mark all items that are rejected as indexed.
    $ret = array_diff($ret, array_keys($extracted_items));
    // Items that are rejected should also be deleted from the server.
    if ($ret) {
      $this->getServer()->deleteItems($this, $ret);
      if (!$extracted_items) {
        return $ret;
      }
    }

    // Return the IDs of all items that were either successfully indexed or
    // rejected before being handed to the server.
    return array_merge($ret, $this->getServer()->indexItems($this, $extracted_items));
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($only_indexed = TRUE, $get_additional = FALSE) {
    $only_indexed = $only_indexed ? 1 : 0;

     // First, try the static cache and the persistent cache bin.
    $cid = $this->getCacheId();
    if (empty($this->fields)) {
      if ($cached = \Drupal::cache()->get($cid)) {
        $this->fields = $cached->data;
      }
    }

    // If not cached, fetch the list of fields and their properties
    if (empty($this->fields[$only_indexed])) {
      $this->fields = array(
        0 => array(
          'fields' => array(),
          'additional fields' => array(),
        ),
        1 => array(
          'fields' => array(),
          // This should never be used, but we still include it to be on the
          // safe side.
          'additional fields' => array(),
        ),
      );
      $this->convertPropertyDefinitionsToFields($this->getDatasource()->getPropertyDefinitions());
      $tags['search_api_index'] = $this->id();
      \Drupal::cache()->set($cid, $this->fields, Cache::PERMANENT, $tags);
    }

    return $get_additional ? $this->fields[$only_indexed] : $this->fields[$only_indexed]['fields'];
  }

  /**
   * Converts an array of property definitions into the index fields format.
   *
   * Stores the resulting values in $this->fields, to be returned by subsequent
   * getFields() calls.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   An array of properties on some complex data object.
   * @param string $prefix
   *   Internal use only. A prefix to use for the generated field names in this
   *   method.
   * @param string $prefix_label
   *   Internal use only. A prefix to use for the generated field labels in this
   *   method.
   */
  protected function convertPropertyDefinitionsToFields(array $properties, $prefix = '', $prefix_label = '') {
    $type_mapping = search_api_field_type_mapping();
    $fields = &$this->fields[0]['fields'];
    $recurse_for_prefixes = isset($this->options['additional fields']) ? $this->options['additional fields'] : array();

    // Loop over all properties and handle them accordingly.
    $recurse = array();
    foreach ($properties as $key => $property) {
      $key = "$prefix$key";
      $label = $prefix_label . $property->getLabel();
      $description = $property->getDescription();
      while ($property instanceof ListDataDefinitionInterface) {
        $property = $property->getItemDefinition();
      }
      while ($property instanceof DataReferenceDefinitionInterface) {
        $property = $property->getTargetDefinition();
      }
      if ($property instanceof ComplexDataDefinitionInterface) {
        $main_property = $property->getMainPropertyName();
        $nested_properties = $property->getPropertyDefinitions();
        $additional = count($nested_properties) > 1;
        if (!empty($recurse_for_prefixes[$key])) {
          if ($nested_properties) {
            // We allow the main property to be indexed directly, so we don't
            // have to add it again for the nested fields.
            if ($main_property) {
              unset($nested_properties[$main_property]);
            }
            if ($nested_properties) {
              $additional = TRUE;
              $recurse[] = array($nested_properties, "$key:", "$label » ");
            }
          }
        }
        if ($additional) {
          $this->fields[0]['additional fields'][$key] = array(
            'name' => "$label [$key]",
            'enabled' => !empty($recurse_for_prefixes[$key]),
          );
        }
        // If the complex data type has a main property, we can index that
        // directly here. Otherwise, we don't add it and continue with the next
        // property.
        if (!$main_property) {
          continue;
        }
        $property = $property->getPropertyDefinition($main_property);
        if (!$property) {
          continue;
        }
      }
      $type = $property->getDataType();
      $fields[$key] = array(
        'name' => $label,
        'description' => $description,
        'indexed' => FALSE,
        'type' => $type_mapping[$type],
        'boost' => '1.0',
      );
      if (isset($this->options['fields'][$key])) {
        $fields[$key] = $this->options['fields'][$key] + $fields[$key];
        $fields[$key]['indexed'] = TRUE;
        $this->fields[1]['fields'][$key] = $fields[$key];
      }
    }
    foreach ($recurse as $arguments) {
      call_user_func_array(array($this, 'convertPropertyDefinitionsToFields'), $arguments);
    }
  }

  /**
   * {@inheritdoc}
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
   * Get the processors that belong to the index.
   *
   * @param bool $all
   * @param string $sortBy
   * @return array|\Drupal\search_api\Processor\ProcessorInterface[]
   */
  public function getProcessors($all = FALSE, $sortBy = 'weight') {
    /** @var $processorPluginManager \Drupal\search_api\Processor\ProcessorPluginManager */
    $processorPluginManager = \Drupal::service('search_api.processor.plugin.manager');

    // Get the processor definitions
    $processor_definitions = $processorPluginManager->getDefinitions();

    // Fetch our active Processors for this index
    $processors_settings = $this->getOption('processors');

    // Only do this if we do not already have our processors
    foreach ($processor_definitions as $name => $processor_definition) {
      // Instantiate the processors
      if (class_exists($processor_definition['class'])) {

        // Give it some sensible weight default so we can return them in order
        if (empty($processors_settings[$name])) {
          $processors_settings[$name] = array('weight' => 0, 'status' => 0);
        }

        if (empty($this->processors[$name])) {
          // Create our settings for this processor
          $settings = empty($processors_settings[$name]['settings']) ? array() : $processors_settings[$name]['settings'];
          $settings['index'] = $this;

          /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
          $processor = $processorPluginManager->createInstance($name, $settings);
          if (!($processor instanceof ProcessorInterface)) {
            watchdog('search_api', t('Processor @id is not an ProcessorInterface instance using @class.', array('@id' => $name, '@class' => $processor_definition['class'])), NULL, WATCHDOG_WARNING);
          }
          if ($processor->supportsIndex($this)) {
            $this->processors[$name] = $processor;
          }
        }
      }
      else {
        watchdog('search_api', t('Processor @id specifies an non-existing @class.', array('@id' => $name, '@class' => $processor_definition['class'])), NULL, WATCHDOG_WARNING);
      }
    }

    if ($sortBy == 'weight') {
      // Sort by weight
      uasort($processors_settings, array('\Drupal\search_api\Utility\Utility', 'sortByWeight'));
    }
    else {
      ksort($processors_settings);
    }

    // Do the return part
    $active_processors = array();
    // Find out which ones are enabled
    foreach ($processors_settings as $name => $processor_setting) {
      // Find out which ones we want
      if ($all || $processor_setting['status']) {
        if (!empty($this->processors[$name])) {
          $active_processors[$name] = $this->processors[$name];
        }
      }
    }

    return $active_processors;
  }

  /**
   * Preprocesses data items for indexing.
   *
   * Lets all enabled processors for this index preprocess the indexed data.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessIndexItems($items);
    }
  }

  /**
   * Preprocesses a search query.
   *
   * Lets all enabled processors for this index preprocess the search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    foreach ($this->getProcessors() as $processor) {
      $processor->preprocessSearchQuery($query);
    }
  }

  /**
   * Postprocesses search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results. See
   *   \Drupal\search_api\Plugin\search_api\QueryInterface::execute() for the
   *   detailed format.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query) {
    foreach (array_reverse($this->getProcessors()) as $processor) {
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $processor->postprocessSearchResults($response, $query);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function index($limit = '-1') {
    $next_set = $this->getDatasource()->getRemainingItems($limit);
    $items = $this->getDatasource()->loadMultiple($next_set);
    $items_status = $this->indexItems($items);
  }

  /**
   * {@inheritdoc}
   */
  public function reindex() {
    $this->getDatasource()->trackUpdate();
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    $this->getServer()->deleteAllItems($this);
    $this->getDatasource()->trackUpdate();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCaches() {
    $this->datasourcePluginInstance = NULL;
    $this->server = NULL;
    $this->fields = NULL;
    $this->fulltextFields = NULL;
    $this->processors = NULL;
    \Drupal::cache()->deleteTags(array('search_api_index' => $this->id()));
  }

  /**
   * Check if the server is enabled
   *
   * @return bool
   */
  public function isServerEnabled() {
    return ($this->hasValidServer() && $this->getServer()->status());
  }

  /**
   * Stops enabling of indexes when the server is disabled
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->status() && !$this->isServerEnabled()) {
      $this->setStatus(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(array $options = array()) {
    if (!$this->status()) {
      throw new SearchApiException(t('Cannot search on a disabled index.'));
    }
    return Utility::createQuery($this, $options);
  }

  /**
   * Execute necessary tasks for a created or updated index.
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    try {
      if ($this->server) {
        // Tell the server about the new index.
        $this->server->addIndex($this);
        if ($this->status()) {
          // $this->queueItems();
        }
      }
      if ($this->getDatasource()) {
        if ((!$update && $this->status()) || ($this->status() && !$this->original->status())) {
          // Start tracking
          $this->getDatasource()->startTracking();
        }
        elseif ($update && !$this->status() && $this->original->status()) {
          // Stop tracking
          $this->getDatasource()->stopTracking();
        }
        elseif ($update && $this->status() && $this->original->status()) {
          $current_configuration = $this->getDatasource()->getConfiguration();
          $previous_configuration = $this->original->getDatasource()->getConfiguration();

          if ($current_configuration['default'] != $previous_configuration['default'] || $current_configuration['bundles'] != $previous_configuration['bundles']) {
            $this->getDatasource()->stopTracking();
            $this->getDatasource()->startTracking();
          }
        }
      }

      $this->resetCaches();
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
    }
  }

  /**
   * Execute necessary tasks for deletion an index
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      $datasource = $entity->getDatasource();
      if (!empty($datasource)) {
        $entity->getDatasource()->stopTracking();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLastIndexed() {
    return \Drupal::state()->get($this->id() . '.last_indexed', array('changed' => '0', 'item_id' => '0'));
  }

  /**
   * {@inheritdoc}
   */
  public function setLastIndexed($changed, $item_id) {
    return \Drupal::state()->set($this->id() . '.last_indexed', array('changed' => $changed, 'item_id' => $item_id));
  }
}
