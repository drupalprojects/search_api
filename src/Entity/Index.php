<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
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
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\search_api\IndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\IndexForm",
 *       "edit" = "Drupal\search_api\Form\IndexForm",
 *       "fields" = "Drupal\search_api\Form\IndexFieldsForm",
 *       "filters" = "Drupal\search_api\Form\IndexFiltersForm",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\IndexDisableConfirmForm",
 *       "reindex" = "Drupal\search_api\Form\IndexReindexConfirmForm",
 *       "clear" = "Drupal\search_api\Form\IndexClearConfirmForm"
 *     },
 *   },
 *   admin_permission = "administer search_api",
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
   * The datasource plugin IDs.
   *
   * @var string[]
   */
  public $datasourcePluginIds = array();

  /**
   * The configuration for the datasource plugins.
   *
   * @var array
   */
  public $datasourcePluginConfigs = array();

  /**
   * The datasource plugin instances.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface[]
   *
   * @see getDatasources()
   */
  protected $datasourcePluginInstances;

  /**
   * The tracker plugin ID.
   *
   * @var string
   */
  public $trackerPluginId;

  /**
   * The tracker plugin configuration.
   *
   * @var array
   */
  public $trackerPluginConfig = array();

  /**
   * The tracker plugin instance.
   *
   * @var \Drupal\search_api\Tracker\TrackerInterface
   */
  protected $trackerPluginInstance;

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
   * List of types that failed to map to a Search API type.
   *
   * The unknown types are the keys and map to arrays of fields that were
   * ignored because they are of this type.
   *
   * @var array
   */
  protected $unmappedFields = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    // Perform default instance construction.
    parent::__construct($values, $entity_type);

    // Check if the tracker plugin ID is not configured.
    if ($this->trackerPluginId === NULL) {
      // Set tracker plugin ID to the default tracker.
      $this->trackerPluginId = \Drupal::config('search_api.settings')->get('default_tracker');
    }

    // Merge in default options.
    $this->options += array(
      'cron_limit' => \Drupal::configFactory()->get('search_api.settings')->get('cron_limit'),
      'index_directly' => FALSE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __clone() {
    // Prevent the datasources, tracker and server instance from being cloned.
    $this->datasourcePluginInstances = $this->trackerPluginInstance = $this->server = NULL;
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
  public function getDatasourceIds() {
    return $this->datasourcePluginIds;
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
      throw new SearchApiException(t('The datasource with ID "@datasource" could not be retrieved for index %index.', $args));
    }
    return $datasources[$datasource_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasources() {
    if (!isset($this->datasourcePluginInstances)) {
      $this->datasourcePluginInstances = array();
      $plugin_manager = \Drupal::service('plugin.manager.search_api.datasource');
      foreach ($this->datasourcePluginIds as $datasource) {
        $config = array('index' => $this);
        if (isset($this->datasourcePluginConfigs[$datasource])) {
          $config += $this->datasourcePluginConfigs[$datasource];
        }
        $this->datasourcePluginInstances[$datasource] = $plugin_manager->createInstance($datasource, $config);
      }
    }

    return $this->datasourcePluginInstances;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidTracker() {
    $tracker_plugin_definition = \Drupal::service('plugin.manager.search_api.tracker')->getDefinition($this->getTrackerId());
    return !empty($tracker_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackerId() {
    return $this->trackerPluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getTracker() {
    // Check if the tracker plugin instance needs to be resolved.
    if (!$this->trackerPluginInstance && $this->hasValidTracker()) {
      // Get the plugin configuration for the tracker.
      $tracker_plugin_configuration = array('index' => $this) + $this->trackerPluginConfig;
      // Create a tracker plugin instance.
      $this->trackerPluginInstance = \Drupal::service('plugin.manager.search_api.tracker')->createInstance($this->getTrackerId(), $tracker_plugin_configuration);
    }
    return $this->trackerPluginInstance;
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
   * {@inheritdoc}
   */
  public function indexItems(array $items) {
    if (!$items || $this->readOnly) {
      return array();
    }
    if (!$this->status) {
      throw new SearchApiException(t("Couldn't index values on index %index (index is disabled)", array('%index' => $this->label())));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on index %index (no fields selected)", array('%index' => $this->label())));
    }

    // To enable proper extraction of fields with Utility::extractFields(),
    // $fields will contain the information from $this->options['fields'], but
    // in a two-dimensional array, keyed by both parts of the field identifier
    // separately – i.e, first by datasource ID, then by property path.
    $fields = array();
    foreach ($this->options['fields'] as $key => $field) {
      // Include real type, if known.
      if (isset($field['real_type'])) {
        $custom_type = $field['real_type'];
        if ($this->getServer()->supportsDatatype($custom_type)) {
          $field['type'] = $field['real_type'];
        }
      }

      // Copy the field information into the $fields array.
      list ($datasource_id, $property_path) = explode(self::DATASOURCE_ID_SEPARATOR, $key, 2);
      $fields[$datasource_id][$property_path] = $field;
    }

    $extracted_items = array();
    $ret = array();
    foreach ($items as $item_id => $item) {
      list($datasource_id, $raw_id) = Utility::getDataSourceIdentifierFromItemId($item_id);
      if (empty($fields[$datasource_id])) {
        $variables['%index'] = $this->label();
        $variables['%datasource'] = $this->getDatasource($datasource_id)->label();
        throw new SearchApiException(t("Couldn't index values on index %index (no fields selected for datasource %datasource)", $variables));
      }
      $extracted_fields = $fields[$datasource_id];
      Utility::extractFields($item, $extracted_fields);
      $extracted_item = array();
      $extracted_item['#item'] = $item;
      $extracted_item['#item_id'] = $raw_id;
      $extracted_item['#datasource'] = $datasource_id;
      $field_prefix = $datasource_id . self::DATASOURCE_ID_SEPARATOR;
      foreach ($extracted_fields as $property_path => $field) {
        $extracted_item[$field_prefix . $property_path] = $field;
      }
      $extracted_items[$item_id] = $extracted_item;
      // Remember the items that were initially passed.
      $ret[$item_id] = $item_id;
    }

    // Preprocess the indexed items.
    \Drupal::moduleHandler()->alter('search_api_index_items', $extracted_items, $this);
    $this->preprocessIndexItems($extracted_items);

    // Remove all items still in $extracted_items from $ret. Thus, only the
    // rejected items' IDs are still contained in $ret, to later be returned
    // along with the successfully indexed ones.
    foreach ($extracted_items as $item_id => $item) {
      unset($ret[$item_id]);
    }

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
      // Remember the fields for which we couldn't find a mapping.
      $this->unmappedFields = array();
      foreach (array_merge(array(NULL), $this->datasourcePluginIds) as $datasource_id) {
        try {
          $this->convertPropertyDefinitionsToFields($this->getPropertyDefinitions($datasource_id), $datasource_id);
        }
        catch (SearchApiException $e) {
          $variables['%index'] = $this->label();
          watchdog_exception('search_api', $e, '%type while retrieving fields for index %index: !message in %function (line %line of %file).', $variables);
        }
      }
      if ($this->unmappedFields) {
        $vars['@fields'] = array();
        foreach ($this->unmappedFields as $type => $fields) {
          $vars['@fields'][] = implode(', ', $fields) . ' (' . t('type !type', array('!type' => $type)) . ')';
        }
        $vars['@fields'] = implode('; ', $vars['@fields']);
        $vars['@index'] = $this->label();
        watchdog('search_api', 'Warning while retrieving available fields for index @index: could not find a type mapping for the following fields: @fields.', $vars, WATCHDOG_WARNING);
      }
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
   * @param string|null $datasource_id
   *   (optional) The ID of the datasource to which these properties belong.
   * @param string $prefix
   *   Internal use only. A prefix to use for the generated field names in this
   *   method.
   * @param string $prefix_label
   *   Internal use only. A prefix to use for the generated field labels in this
   *   method.
   */
  protected function convertPropertyDefinitionsToFields(array $properties, $datasource_id = NULL, $prefix = '', $prefix_label = '') {
    $type_mapping = Utility::getFieldTypeMapping();
    $fields = &$this->fields[0]['fields'];
    $recurse_for_prefixes = isset($this->options['additional fields']) ? $this->options['additional fields'] : array();

    // All field identifiers should start with the datasource ID.
    if (!$prefix && $datasource_id) {
      $prefix = $datasource_id . self::DATASOURCE_ID_SEPARATOR;
    }
    $name_prefix = $datasource_id ? $this->getDatasource($datasource_id)->label() . ' » ' : '';

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

        // Don't add the additional 'entity' property for entity reference
        // fields which don't target a content entity type.
        // @todo Try to see if there's a better way of doing this check when
        // https://drupal.org/node/2228721 gets fixed.
        if ($property instanceof FieldItemDataDefinition && $property->getDataType() == 'field_item:entity_reference') {
          $entity_type = $this->entityManager()->getDefinition($property->getSetting('target_type'));
          if (!$entity_type->isSubclassOf('\Drupal\Core\TypedData\TypedDataInterface')) {
            unset($nested_properties['entity']);
          }
        }

        $additional = count($nested_properties) > 1;
        if (!empty($recurse_for_prefixes[$key]) && $nested_properties) {
          // We allow the main property to be indexed directly, so we don't
          // have to add it again for the nested fields.
          if ($main_property) {
            unset($nested_properties[$main_property]);
          }
          if ($nested_properties) {
            $additional = TRUE;
            $recurse[] = array($nested_properties, $datasource_id, "$key:", "$label » ");
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
        $parent_type = $property->getDataType();
        $property = $property->getPropertyDefinition($main_property);
        if (!$property) {
          continue;
        }

        // If there are additional properties, add the label for the main
        // property to make it clear what it refers to.
        if ($additional) {
          $label .= ' » ' . $property->getLabel();
        }
      }

      $type = $property->getDataType();
      // Try to see if there's a mapping for a parent.child data type.
      if (isset($parent_type) && isset($type_mapping[$parent_type . '.' . $type])) {
        $field_type = $type_mapping[$parent_type . '.' . $type];
      }
      elseif (!empty($type_mapping[$type])) {
        $field_type = $type_mapping[$type];
      }
      else {
        // Failed to map this type, skip.
        if (!isset($type_mapping[$type])) {
          $this->unmappedFields[$type][$key] = $key;
        }
        continue;
      }

      $fields[$key] = array(
        'name' => $label,
        'name_prefix' => $name_prefix,
        'description' => $description,
        'datasource' => $datasource_id,
        'indexed' => FALSE,
        'type' => $field_type,
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

    // Sort the fields, only do it if the key is empty to avoid unnecessary
    // processing.
    uasort($fields, '\Drupal\search_api\Entity\Index::sortField');
  }

  /**
   * Helper callback for uasort() to sort configuration entities by weight and label.
   */
  public static function sortField($field_a, $field_b) {
    $a_label = $field_a['name'];
    $b_label = $field_b['name'];
    return strnatcasecmp($a_label, $b_label);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions($datasource_id, $alter = TRUE) {
    $properties = array();
    $datasource = NULL;
    if ($datasource_id) {
      $datasource = $this->getDatasource($datasource_id);
      $properties = $datasource->getPropertyDefinitions();
    }
    if ($alter) {
      foreach ($this->getProcessors() as $processor) {
        $processor->alterPropertyDefinitions($properties, $datasource);
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getFulltextFields($only_indexed = TRUE) {
    $i = $only_indexed ? 1 : 0;
    $fields = array();
    if (!isset($this->fulltextFields[$i])) {
      $this->fulltextFields[$i] = array();
      if ($only_indexed) {
        if (isset($this->options['fields'])) {
          $fields = $this->options['fields'];
        }
      }
      else {
        $fields = $this->getFields(FALSE);
      }
      foreach ($fields as $key => $field) {
        if (Utility::isTextType($field['type'])) {
          $this->fulltextFields[$i][] = $key;
        }
      }
    }
    return $this->fulltextFields[$i];
  }

  /**
   * {@inheritdoc}
   */
  public function loadItem($item_id) {
    $items = $this->loadItemsMultiple(array($item_id), TRUE);
    return $items ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadItemsMultiple(array $item_ids, $flat = FALSE) {
    $items_by_datasource = array();
    foreach ($item_ids as $item_id) {
      list($datasource_id, $raw_id) = explode(self::DATASOURCE_ID_SEPARATOR, $item_id);
      $items_by_datasource[$datasource_id][$item_id] = $raw_id;
    }
    $items = array();
    foreach ($items_by_datasource as $datasource_id => $raw_ids) {
      try {
        foreach ($this->getDatasource($datasource_id)->loadMultiple($raw_ids) as $raw_id => $item) {
          $id = $datasource_id . self::DATASOURCE_ID_SEPARATOR . $raw_id;
          if ($flat) {
            $items[$id] = $item;
          }
          else {
            $items[$datasource_id][$id] = $item;
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
  public function getProcessors($all = FALSE, $sortBy = 'weight') {
    /** @var $processorPluginManager \Drupal\search_api\Processor\ProcessorPluginManager */
    $processorPluginManager = \Drupal::service('plugin.manager.search_api.processor');

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
            continue;
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
  public function index($limit = '-1', $datasource_id = NULL) {
    if ($this->hasValidTracker() && !$this->isReadOnly()) {
      $tracker = $this->getTracker();
      $next_set = $tracker->getRemainingItems($limit, $datasource_id);
      $items_by_datasource = array();
      foreach ($next_set as $item_id) {
        list($datasource_id, $raw_id) = Utility::getDataSourceIdentifierFromItemId($item_id);
        $items_by_datasource[$datasource_id][] = $raw_id;
      }
      $items = array();
      foreach ($items_by_datasource as $datasource_id => $item_ids) {
        try {
          $prefix = $datasource_id . self::DATASOURCE_ID_SEPARATOR;
          foreach ($this->getDatasource($datasource_id)->loadMultiple($item_ids) as $raw_id => $item) {
            $items["$prefix$raw_id"] = $item;
          }
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
      if ($items) {
        try {
          $ids_indexed = $this->indexItems($items);
          $tracker->trackItemsIndexed($ids_indexed);
          return count($ids_indexed);
        }
        catch (SearchApiException $e) {
          $variables['%index'] = $this->label();
          watchdog_exception('search_api', $e, '%type while trying to index items on index %index: !message in %function (line %line of %file)', $variables);
        }
      }
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function reindex() {
    if ($this->status() && !$this->isReadOnly() && $this->hasValidTracker()) {
      $this->getTracker()->trackAllItemsUpdated();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsInserted($datasource_id, array $ids) {
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = $datasource_id . self::DATASOURCE_ID_SEPARATOR . $id;
      }
      $this->getTracker()->trackItemsInserted($item_ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsUpdated($datasource_id, array $ids) {
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = $datasource_id . self::DATASOURCE_ID_SEPARATOR . $id;
      }
      $this->getTracker()->trackItemsUpdated($item_ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsDeleted($datasource_id, array $ids) {
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = $datasource_id . self::DATASOURCE_ID_SEPARATOR . $id;
      }
      $this->getTracker()->trackItemsDeleted($item_ids);
      if ($this->isServerEnabled()) {
        $this->getServer()->deleteItems($this, $item_ids);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    if ($this->reindex() && $this->isServerEnabled()) {
      $this->getServer()->deleteAllItems($this);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCaches() {
    $this->datasourcePluginInstances = NULL;
    $this->trackerPluginInstance = NULL;
    $this->server = NULL;
    $this->fields = NULL;
    $this->fulltextFields = NULL;
    $this->processors = NULL;
    Cache::invalidateTags(array('search_api_index' => array($this->id())));
  }

  /**
   * {@inheritdoc}
   */
  public function isServerEnabled() {
    return ($this->hasValidServer() && $this->getServer()->status());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Stop enabling of indexes when the server is disabled.
    if ($this->status() && !$this->isServerEnabled()) {
      $this->disable();
    }

    // Always enable the "Langue control" processor and corresponding "Item
    // language" field.
    // @todo Replace this with a cleaner, more flexible approach. See
    // https://drupal.org/node/2090341
    $this->options['processors']['search_api_language_processor']['status'] = TRUE;
    $this->options['fields']['search_api_language'] = array('type' => 'string');
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
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $this->resetCaches();
    try {
      // Fake an original for inserts to make code cleaner.
      /** @var \Drupal\search_api\Index\IndexInterface $original */
      $original = $update ? $this->original : entity_create($this->getEntityTypeId(), array('status' => FALSE));

      if ($this->status() && $original->status()) {
        // On option changes
        $this->actOnServerSwitch($original);
        $this->actOnDatasourceSwitch($original);
        $this->actOnTrackerSwitch($original);
      }
      else if (!$this->status() && $original->status()) {
        // Stop tracking if the index switched to disabled
        if ($this->hasValidTracker()) {
          $this->stopTracking();
        }
        if ($original->isServerEnabled()) {
          // Let the server know we are disabling the index
          $original->getServer()->removeIndex($this);
        }
      }
      else if ($this->status() && !$original->status()) {
        // Add the index to the server.
        $this->getServer()->addIndex($this);
        // Start tracking.
        $this->startTracking();
      }

      $this->resetCaches();
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
    }
  }

  /**
   * Actions to take if the index switches servers.
   *
   * @param \Drupal\search_api\Index\IndexInterface $original
   *   The previous version of the index.
   */
  public function actOnServerSwitch(IndexInterface $original) {
    if ($this->getServerId() != $original->getServerId()) {
      // Remove from old server if there was an old server assigned to the
      // index.
      if ($original->isServerEnabled()) {
        $original->getServer()->removeIndex($this);
      }
      // Add to new server
      if ($this->isServerEnabled()) {
        $this->getServer()->addIndex($this);
      }
      // When the server changes we also need to trigger reindex.
      $this->reindex();
    }
    elseif ($this->isServerEnabled()) {
      // Tell the server the index configuration got updated
      $this->getServer()->updateIndex($this);
    }
  }

  /**
   * Actions to take when datasources change.
   *
   * @param \Drupal\search_api\Index\IndexInterface $original
   *   The previous version of the index.
   */
  public function actOnDatasourceSwitch(IndexInterface $original) {
    // Take the old datasource list
    if ($this->datasourcePluginIds != $original->getDatasourceIds()) {
      // Get the difference between the arrays
      $removed = array_diff($original->getDatasourceIds(), $this->datasourcePluginIds);
      $added = array_diff($this->datasourcePluginIds, $original->getDatasourceIds());
      // Delete from tracker if the datasource got removed
      foreach ($removed as $datasource_id) {
        $this->getTracker()->trackAllItemsDeleted($datasource_id);
      }
      // Add to the tracker if the datasource got added
      foreach ($added as $datasource_id) {
        $datasource = $this->getDatasource($datasource_id);
        $item_ids = $datasource->getItemIds();
        $this->trackItemsInserted($datasource_id, $item_ids);
      }
    }
  }


  /**
   * Actions to take when trackers change.
   *
   * @param \Drupal\search_api\Index\IndexInterface $original
   *   The previous version of the index.
   */
  public function actOnTrackerSwitch(IndexInterface $original) {
    // Take the old datasource list
    if ($this->trackerPluginId != $original->getTrackerId()) {
      // Delete from old tracker
      $original->stopTracking();
      // Add to the tracker if the datasource got added
      $this->startTracking();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    /** @var \Drupal\search_api\Index\IndexInterface[] $entities */
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
  public function stopTracking() {
    foreach ($this->getDatasources() as $datasource) {
      $this->getTracker()->trackAllItemsDeleted($datasource->getPluginId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function startTracking() {
    foreach ($this->getDatasources() as $datasource) {
      // Check whether there are entities which need to be inserted.
      if (($item_ids = $datasource->getItemIds())) {
        // Register entities with the tracker.
        $this->trackItemsInserted($datasource->getPluginId(), $item_ids);
      }
    }
  }

}
