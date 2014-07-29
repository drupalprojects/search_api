<?php

/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal\Component\Utility\String;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Item\GenericFieldInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
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
  public $read_only = FALSE;

  /**
   * An array of options for configuring this index.
   *
   * @var array
   *
   * @see getOptions()
   */
  public $options = array();

  /**
   * The datasource plugin IDs.
   *
   * @var string[]
   */
  public $datasources = array();

  /**
   * The configuration for the datasource plugins.
   *
   * @var array
   */
  public $datasource_configs = array();

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
  public $tracker;

  /**
   * The tracker plugin configuration.
   *
   * @var array
   */
  public $tracker_config = array();

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
  public $server;

  /**
   * The server object instance.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $serverInstance;

  /**
   * Cached properties for this index's datasources.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface[][][]
   */
  protected $properties = array();

  /**
   * Cached fields data.
   *
   * The array contains two elements: 0 for all fields, 1 for indexed fields.
   * The elements under these keys are arrays with keys "fields" and "additional
   * fields", corresponding to return values for getFields() and
   * getAdditionalFields(), respectively.
   *
   * @var \Drupal\search_api\Item\GenericFieldInterface[][][]
   *
   * @see computeFields()
   * @see getFields()
   * @see getFieldsByDatasource()
   * @see getAdditionalFields()
   */
  protected $fields;

  /**
   * Cached fields data, grouped by datasource and indexed state.
   *
   * The array is three-dimensional, with the first two keys corresponding to
   * the parameters of a getFieldsByDatasource() call and the last one being the
   * field ID.
   *
   * @var \Drupal\search_api\Item\FieldInterface[][][]
   *
   * @see getFieldsByDatasource()
   */
  protected $datasourceFields;

  /**
   * Cached additional fields data, grouped by datasource.
   *
   * The array is two-dimensional, with the first key corresponding to the
   * datasource ID and the second key being a field ID.
   *
   * @var \Drupal\search_api\Item\FieldInterface[][]
   *
   * @see getAdditionalFieldsByDatasource()
   */
  protected $datasourceAdditionalFields;

  /**
   * Cached fulltext fields data for getFulltextFields().
   *
   * @var array
   */
  protected $fulltextFields;

  /**
   * Cached return value for getProcessors().
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
    if ($this->tracker === NULL) {
      // Set tracker plugin ID to the default tracker.
      $this->tracker = \Drupal::config('search_api.settings')->get('default_tracker');
    }

    // Merge in default options.
    // @todo Use a dedicated method, like defaultConfiguration() for plugins?
    $this->options += array(
      'cron_limit' => \Drupal::configFactory()->get('search_api.settings')->get('cron_limit'),
      'index_directly' => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __clone() {
    // Prevent the datasources, tracker and server instance from being cloned.
    $this->datasourcePluginInstances = $this->trackerPluginInstance = $this->serverInstance = NULL;
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
    return $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    $options = $this->getOptions();
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
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $option) {
    $this->options[$name] = $option;
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
      foreach ($this->datasources as $datasource) {
        $config = array('index' => $this);
        if (isset($this->datasource_configs[$datasource])) {
          $config += $this->datasource_configs[$datasource];
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
    $tracker_plugin_definition = \Drupal::service('plugin.manager.search_api.tracker')->getDefinition($this->getTrackerId(), FALSE);
    return !empty($tracker_plugin_definition);
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
    // Check if the tracker plugin instance needs to be resolved.
    if (!$this->trackerPluginInstance) {
      // Get the plugin configuration for the tracker.
      $tracker_plugin_configuration = array('index' => $this) + $this->tracker_config;
      // Try to create a tracker plugin instance.
      if (!($this->trackerPluginInstance = \Drupal::service('plugin.manager.search_api.tracker')->createInstance($this->getTrackerId(), $tracker_plugin_configuration))) {
        $args['@tracker'] = $this->tracker;
        $args['%index'] = $this->label();
        throw new SearchApiException(t('The tracker with ID "@tracker" could not be retrieved for index %index.', $args));
      }
    }

    return $this->trackerPluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidServer() {
    return $this->server !== NULL && \Drupal::entityManager()->getStorage('search_api_server')->load($this->server) !== NULL;
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
    // Check if the server needs to be resolved.
    if (!$this->serverInstance && $this->server) {
      // Try to get the server from the storage.
      if (!($this->serverInstance = \Drupal::entityManager()->getStorage('search_api_server')->load($this->server))) {
        $args['@server'] = $this->server;
        $args['%index'] = $this->label();
        throw new SearchApiException(t('The server with ID "@server" could not be retrieved for index %index.', $args));
      }
    }

    return $this->serverInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server = NULL) {
    // Overwrite the current server instance.
    $this->serverInstance = $server;
    // Overwrite the server machine name.
    $this->server = $server ? $server->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(array $search_objects) {
    if (!$search_objects || $this->read_only) {
      return array();
    }
    if (!$this->status) {
      throw new SearchApiException(t("Couldn't index values on index %index (index is disabled)", array('%index' => $this->label())));
    }
    if (empty($this->options['fields'])) {
      throw new SearchApiException(t("Couldn't index values on index %index (no fields selected)", array('%index' => $this->label())));
    }

    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    $items = array();
    foreach ($search_objects as $item_id => $object) {
      $items[$item_id] = Utility::createItemFromObject($this, $object, $item_id);
      $items[$item_id]->getFields();
    }

    // Remember the items that were initially passed.
    $ret = array_keys($items);
    $ret = array_combine($ret, $ret);

    // Preprocess the indexed items.
    \Drupal::moduleHandler()->alter('search_api_index_items', $items, $this);
    $this->preprocessIndexItems($items);

    // Remove all items still in $items from $ret. Thus, only the rejected
    // items' IDs are still contained in $ret, to later be returned along with
    // the successfully indexed ones.
    foreach ($items as $item_id => $item) {
      unset($ret[$item_id]);
    }

    // Items that are rejected should also be deleted from the server.
    if ($ret) {
      $this->getServer()->deleteItems($this, $ret);
      if (!$items) {
        return $ret;
      }
    }

    // Return the IDs of all items that were either successfully indexed or
    // rejected before being handed to the server.
    return array_merge(array_values($ret), array_values($this->getServer()->indexItems($this, $items)));
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($only_indexed = TRUE) {
    $this->computeFields();
    $only_indexed = $only_indexed ? 1 : 0;
    return $this->fields[$only_indexed]['fields'];
  }

  /**
   * Populates the $fields property with information about the index's fields.
   *
   * Used by getFields(), getFieldsByDatasource() and getAdditionalFields().
   */
  protected function computeFields() {
    // First, try the static cache and the persistent cache bin.
    $cid = $this->getCacheId();
    if (empty($this->fields)) {
      if ($cached = \Drupal::cache()->get($cid)) {
        $this->fields = $cached->data;
        if ($this->fields) {
          $this->updateFieldsIndex($this->fields);
        }
      }
    }

    // If not cached, fetch the list of fields and their properties.
    if (empty($this->fields)) {
      $this->fields = array(
        0 => array(
          'fields' => array(),
          'additional fields' => array(),
        ),
        1 => array(
          'fields' => array(),
        ),
      );
      // Remember the fields for which we couldn't find a mapping.
      $this->unmappedFields = array();
      foreach (array_merge(array(NULL), $this->datasources) as $datasource_id) {
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
          $vars['@fields'][] = implode(', ', $fields) . ' (' . String::format('type !type', array('!type' => $type)) . ')';
        }
        $vars['@fields'] = implode('; ', $vars['@fields']);
        $vars['@index'] = $this->label();
        watchdog('search_api', 'Warning while retrieving available fields for index @index: could not find a type mapping for the following fields: @fields.', $vars, WATCHDOG_WARNING);
      }
      $tags['search_api_index'] = $this->id();
      \Drupal::cache()->set($cid, $this->fields, Cache::PERMANENT, $tags);
    }
  }

  /**
   * Sets this object as the index for all fields contained in the given array.
   *
   * This is important when loading fields from the cache, because their index
   * objects might then point to another instance of this index.
   *
   * @param array $fields
   *   An array containing various values, some of which might be
   *   \Drupal\search_api\Item\GenericFieldInterface objects and some of which
   *   might be nested arrays containing such objects.
   */
  protected function updateFieldsIndex(array $fields) {
    foreach ($fields as $value) {
      if (is_array($value)) {
        $this->updateFieldsIndex($value);
      }
      elseif ($value instanceof GenericFieldInterface) {
        $value->setIndex($this);
      }
    }
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
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If $datasource_id is no valid datasource for this index.
   */
  protected function convertPropertyDefinitionsToFields(array $properties, $datasource_id = NULL, $prefix = '', $prefix_label = '') {
    $type_mapping = Utility::getFieldTypeMapping();
    $field_options = isset($this->options['fields']) ? $this->options['fields'] : array();
    $enabled_additional_fields = isset($this->options['additional fields']) ? $this->options['additional fields'] : array();

    // All field identifiers should start with the datasource ID.
    if (!$prefix && $datasource_id) {
      $prefix = $datasource_id . self::DATASOURCE_ID_SEPARATOR;
    }
    $label_prefix = $datasource_id ? $this->getDatasource($datasource_id)->label() . ' » ' : '';

    // Loop over all properties and handle them accordingly.
    $recurse = array();
    foreach ($properties as $property_path => $property) {
      $key = "$prefix$property_path";
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
        if (!empty($enabled_additional_fields[$key]) && $nested_properties) {
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
          $additional_field = Utility::createAdditionalField($this, $key);
          $additional_field->setLabel("$label [$key]");
          $additional_field->setDescription($description);
          $additional_field->setEnabled(!empty($enabled_additional_fields[$key]));
          $additional_field->setLocked(FALSE);
          $this->fields[0]['additional fields'][$key] = $additional_field;
          if ($additional_field->isEnabled()) {
            while ($pos = strrpos($property_path, ':')) {
              $property_path = substr($property_path, 0, $pos);
              /** @var \Drupal\search_api\Item\AdditionalFieldInterface $additional_field */
              $additional_field = $this->fields[0]['additional fields'][$property_path];
              $additional_field->setEnabled(TRUE);
              $additional_field->setLocked(TRUE);
            }
          }
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

      $field = Utility::createField($this, $key);
      $field->setType($field_type);
      $field->setLabel($label);
      $field->setLabelPrefix($label_prefix);
      $field->setDescription($description);
      $field->setIndexed(FALSE);
      $this->fields[0]['fields'][$key] = $field;
      if (isset($field_options[$key])) {
        $field->setIndexed(TRUE);
        $field->setType($field_options[$key]['type']);
        if (isset($field_options[$key]['boost'])) {
          $field->setBoost($field_options[$key]['boost']);
        }
        $this->fields[1]['fields'][$key] = $field;
      }
    }
    foreach ($recurse as $arguments) {
      call_user_func_array(array($this, 'convertPropertyDefinitionsToFields'), $arguments);
    }

    uasort($this->fields[0]['fields'], '\Drupal\search_api\Entity\Index::sortField');
  }

  /**
   * Compares two fields by their labels.
   *
   * Used as a callback for uasort() in convertPropertyDefinitionsToFields().
   *
   * @param \Drupal\search_api\Item\GenericFieldInterface $field1
   *   The first field.
   * @param \Drupal\search_api\Item\GenericFieldInterface $field2
   *   The second field.
   *
   * @return int
   *   An integer less than, equal to, or greater than zero if the first
   *   argument is considered to be respectively less than, equal to, or greater
   *   than the second.
   */
  public static function sortField(GenericFieldInterface $field1, GenericFieldInterface $field2) {
    return strnatcasecmp($field1->getLabel(), $field2->getLabel());
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsByDatasource($datasource_id, $only_indexed = TRUE) {
    $only_indexed = $only_indexed ? 1 : 0;
    if (!isset($this->datasourceFields)) {
      $this->computeFields();
      $this->datasourceFields = array_fill_keys($this->datasources, array(array(), array()));
      $this->datasourceFields[NULL] = array(array(), array());
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($this->fields[0]['fields'] as $field_id => $field) {
        $this->datasourceFields[$field->getDatasourceId()][0][$field_id] = $field;
        if ($field->isIndexed()) {
          $this->datasourceFields[$field->getDatasourceId()][1][$field_id] = $field;
        }
      }
    }
    return $this->datasourceFields[$datasource_id][$only_indexed];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdditionalFields() {
    $this->computeFields();
    return $this->fields[0]['additional fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdditionalFieldsByDatasource($datasource_id) {
    if (!isset($this->datasourceAdditionalFields)) {
      $this->computeFields();
      $this->datasourceAdditionalFields = array_fill_keys($this->datasources, array());
      $this->datasourceAdditionalFields[NULL] = array();
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($this->fields[0]['additional fields'] as $field_id => $field) {
        $this->datasourceAdditionalFields[$field->getDatasourceId()][$field_id] = $field;
      }
    }
    return $this->datasourceAdditionalFields[$datasource_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions($datasource_id, $alter = TRUE) {
    $alter = $alter ? 1 : 0;
    if (!isset($this->properties[$datasource_id][$alter])) {
      if ($datasource_id) {
        $datasource = $this->getDatasource($datasource_id);
        $this->properties[$datasource_id][$alter] = $datasource->getPropertyDefinitions();
      }
      else {
        $datasource = NULL;
        $this->properties[$datasource_id][$alter] = array();
      }
      if ($alter) {
        foreach ($this->getProcessors() as $processor) {
          $processor->alterPropertyDefinitions($this->properties[$datasource_id][$alter], $datasource);
        }
      }
    }
    return $this->properties[$datasource_id][$alter];
  }

  /**
   * {@inheritdoc}
   */
  public function getFulltextFields($only_indexed = TRUE) {
    $i = $only_indexed ? 1 : 0;
    if (!isset($this->fulltextFields[$i])) {
      $this->fulltextFields[$i] = array();
      if ($only_indexed) {
        if (isset($this->options['fields'])) {
          foreach ($this->options['fields'] as $key => $field) {
            if (Utility::isTextType($field['type'])) {
              $this->fulltextFields[$i][] = $key;
            }
          }
        }
      }
      else {
        foreach ($this->getFields(FALSE) as $key => $field) {
          if (Utility::isTextType($field->getType())) {
            $this->fulltextFields[$i][] = $key;
          }
        }
      }
    }
    return $this->fulltextFields[$i];
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
      list($datasource_id, $raw_id) = explode(self::DATASOURCE_ID_SEPARATOR, $item_id);
      $items_by_datasource[$datasource_id][$item_id] = $raw_id;
    }
    $items = array();
    foreach ($items_by_datasource as $datasource_id => $raw_ids) {
      try {
        foreach ($this->getDatasource($datasource_id)->loadMultiple($raw_ids) as $raw_id => $item) {
          $id = $datasource_id . self::DATASOURCE_ID_SEPARATOR . $raw_id;
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
            watchdog('search_api', String::format('Processor @id is not an ProcessorInterface instance using @class.', array('@id' => $name, '@class' => $processor_definition['class'])), NULL, WATCHDOG_WARNING);
            continue;
          }
          if ($processor->supportsIndex($this)) {
            $this->processors[$name] = $processor;
          }
        }
      }
      else {
        watchdog('search_api', String::format('Processor @id specifies an non-existing @class.', array('@id' => $name, '@class' => $processor_definition['class'])), NULL, WATCHDOG_WARNING);
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
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   An array of items to be preprocessed for indexing, passed by reference.
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
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    foreach (array_reverse($this->getProcessors()) as $processor) {
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $processor->postprocessSearchResults($results);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function index($limit = '-1', $datasource_id = NULL) {
    if ($this->hasValidTracker() && !$this->isReadOnly()) {
      $tracker = $this->getTracker();
      $next_set = $tracker->getRemainingItems($limit, $datasource_id);
      $items = $this->loadItemsMultiple($next_set);
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
    if ($this->status()) {
      $this->getTracker()->trackAllItemsUpdated();
    }
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
    if ($this->hasValidTracker() && $this->status()) {
      $item_ids = array();
      foreach ($ids as $id) {
        $item_ids[] = Utility::createCombinedId($datasource_id, $id);
      }
      $this->getTracker()->$tracker_method($item_ids);
      if ($this->options['index_directly']) {
        try {
          $items = $this->loadItemsMultiple($item_ids);
          if ($items) {
            $indexed_ids = $this->indexItems($items);
            if ($indexed_ids) {
              $this->getTracker()->trackItemsIndexed($indexed_ids);
            }
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
    if ($this->status()) {
      $this->reindex();
      if (!$this->isReadOnly()) {
        $this->getServer()->deleteAllIndexItems($this);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetCaches() {
    $this->datasourcePluginInstances = NULL;
    $this->trackerPluginInstance = NULL;
    $this->serverInstance = NULL;
    $this->fields = NULL;
    $this->datasourceFields = NULL;
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

    // Always enable the "Language control" processor and corresponding "Item
    // language" field.
    // @todo Replace this with a cleaner, more flexible approach. See
    // https://drupal.org/node/2090341
    $this->options['processors']['language']['status'] = TRUE;
    $this->options['processors']['language']['weight'] = -50;
    $this->options['processors']['language'] += array('settings' => array());
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

      if (\Drupal::moduleHandler()->moduleExists('views')) {
        views_invalidate_cache();
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
    if ($this->datasources != $original->getDatasourceIds()) {
      // Get the difference between the arrays
      $removed = array_diff($original->getDatasourceIds(), $this->datasources);
      $added = array_diff($this->datasources, $original->getDatasourceIds());
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
    if ($this->tracker != $original->getTrackerId()) {
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
    if (\Drupal::moduleHandler()->moduleExists('views')) {
      views_invalidate_cache();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function stopTracking() {
    if ($this->hasValidTracker()) {
      foreach ($this->getDatasources() as $datasource) {
        $this->getTracker()->trackAllItemsDeleted($datasource->getPluginId());
      }
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

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Indexes that have a server assigned need to depend on it.
    if ($server = $this->getServer()) {
      $this->addDependency('entity', $server->getConfigDependencyName());
    }

    // Add a dependency on the module that provides the tracker for this index.
    if ($tracker = $this->getTracker()) {
      $this->addDependency('module', $tracker->getPluginDefinition()['provider']);
    }

    // Add dependencies on the modules that provide processors for this index.
    foreach ($this->getProcessors() as $processor) {
      $this->addDependency('module', $processor->getPluginDefinition()['provider']);
    }

    // Add the list of datasource dependencies collected from the plugin itself.
    foreach ($this->getDatasources() as $datasource) {
      $this->addDependencies($datasource->calculateDependencies());
    }

    return $this->dependencies;
  }

}
