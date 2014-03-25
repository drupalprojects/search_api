<?php
/**
 * @file
 * Contains \Drupal\search_api\Entity\Index.
 */

namespace Drupal\search_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\Core\Entity;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_index",
 *   label = @Translation("Search index"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\search_api\Handler\IndexAccessHandler",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\IndexForm",
 *       "edit" = "Drupal\search_api\Form\IndexForm",
 *       "fields" = "Drupal\search_api\Form\IndexFieldsForm",
 *       "filters" = "Drupal\search_api\Form\IndexFiltersForm",
 *       "delete" = "Drupal\search_api\Form\IndexDeleteConfirmForm",
 *       "enable" = "Drupal\search_api\Form\IndexEnableConfirmForm",
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
    return $this->serverMachineName !== NULL && \Drupal::entityManager()->getStorageController('search_api_server')->load($this->serverMachineName) !== NULL;
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
    public
    function getServer() {
    // Check if the server needs to be resolved. Note we do not use
    // hasValidServer to prevent duplicate load calls to the storage controller.
    if (!$this->server) {
      // Get the server machine name.
      $server_machine_name = $this->serverMachineName;
      // Get the server from the storage.
      $this->server = \Drupal::entityManager()->getStorageController('search_api_server')->load($server_machine_name);
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
  public function getFields($only_indexed = TRUE, $get_additional = FALSE) {
    // @todo Adapt to ComplexDataInterface.
    $only_indexed = $only_indexed ? 1 : 0;
    $get_additional = $get_additional ? 1 : 0;

     // First, try the static cache and the persistent cache bin.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $cid = $this->getCacheId() . "-" . $only_indexed . "-" . $get_additional;
      if ($cached = \Drupal::cache()->get($cid)) {
        $this->fields[$only_indexed][$get_additional] = $cached->data;
      }
    }
    $this->fields[$only_indexed][$get_additional] = '';

    // Otherwise, we have to compute the result.
    if (empty($this->fields[$only_indexed][$get_additional])) {
      $search_api_index_fields = empty($this->options['fields']) ? array() : $this->options['fields'];
      // Get all entity types
      $entity_types = $this->entityManager()->getDefinitions();
      // Define additional variable
      $additional = array();

      // First we need all already added prefixes.
      $added = ($only_indexed || empty($this->options['additional fields'])) ? array() : $this->options['additional fields'];
      foreach (array_keys($search_api_index_fields) as $key) {
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
      $datasource_definition = $this->getDatasource()->getPluginDefinition();
      $field_entity_types = array($datasource_definition['id'] => $entity_types[$datasource_definition['entity_type']]);

      // The list nesting level for entities with a certain prefix
      $nesting_levels = array('' => 0);
      // Gets the default types for Search API Fields
      $types = search_api_default_field_types();
      // @todo find out what this flat does and give it a better name
      $flat = array();

      // As long as we have not processed all entity types that are attached to our main datasource entity type we need
      // to keep searching for fields that we want to index
      while ($field_entity_types) {
        foreach ($field_entity_types as $prefix => $field_entity_type) {
          /** @var $field_entity_type \Drupal\Core\Entity\EntityTypeInterface */
          dpm($field_entity_type);

          $prefix_name = $field_entity_type->getLabel();
          if (!($field_entity_type instanceof \Drupal\Core\Entity\EntityTypeInterface)) {
            unset($field_entity_types[$prefix]);
            continue;
          }
          $entity_type_id = $field_entity_type->id();

          // Now look at all fields for all bundles for this entity type.
          $bundles = $this->entityManager()->getBundleInfo($entity_type_id);
          foreach ($bundles as $bundle_id => $bundle) {
            $fields = $this->entityManager()->getFieldDefinitions($entity_type_id, $bundle_id);
            foreach ($fields as $field) {
              $name = $field->getName();
              $type = $field->getType();
              $isMultiple = $field->isMultiple();

              // @todo, perhaps a hook that we implement in other modules name
              // Treat Entity API type "token" as our "string" type.
              // Treat list_text as strings for option lists.
              if ($type == 'token' ||  $type == 'list_text') {
                // Inner type is changed to "string".
                $type = 'string';
              }
              if ($type == 'created' || $type == 'changed') {
                $type = 'date';
              }
              // All of the items are lists so we can use the real type without the list.
              $type = str_replace('list_', '', $type);

              $key = $prefix . ':' . $name;
              // @todo: Check if this comparision is valid
              if ((isset($types[$type]) || isset($entity_types[$type])) && (!$only_indexed || !empty($search_api_index_fields[$key]))) {
                if (!empty($search_api_index_fields[$key])) {
                  // This field is already known in the index configuration.
                  $flat[$key] = $search_api_index_fields[$key] + array(
                    'name' => $prefix_name . ' ' . $field->getLabel(),
                    'description' => $field->getDescription(),
                    'boost' => '1.0',
                    'indexed' => TRUE,
                  );
                }
                else {
                  $flat[$key] = array(
                    'name'    => $prefix_name . ' ' . $field->getLabel(),
                    'description' => $field->getDescription(),
                    'type'    => $type,
                    'boost' => '1.0',
                    'indexed' => FALSE,
                  );
                }
              }
              if (empty($types[$type])) {
                if (isset($added[$key])) {
                  // Visit this entity/struct in a later iteration.
                  $field_entity_types[$key . ':'] = $field;
                  $prefix_names[$key . ':'] = $prefix_name . $field->getLabel(). ' » ';
                }
                else {
                  $name = $prefix_name . ' ' . $field->getLabel();
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
          }
          unset($field_entity_types[$prefix]);
        }
      }

      if (!$get_additional) {
        $this->fields[$only_indexed][$get_additional] = $flat;
      }
      else {
        $options = array();
        $options['fields'] = $flat;
        $options['additional fields'] = $additional;
        $this->fields[$only_indexed][$get_additional] =  $options;
      }
      \Drupal::cache()->set($cid, $this->fields[$only_indexed][$get_additional]);
    }

    return $this->fields[$only_indexed][$get_additional];
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
   * Loads all enabled processors for this index in proper order.
   *
   * @return \Drupal\search_api\Processor\ProcessorInterface[]
   *   All enabled processors for this index.
   */
  public function getProcessors() {
    // @todo Implement getProcessors() method.
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
}
