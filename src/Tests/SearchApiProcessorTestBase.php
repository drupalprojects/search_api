<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiProcessorTestBase.
 */

namespace Drupal\search_api\Tests;

use Drupal\search_api\Index\IndexInterface;
use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Search API Processor tests base class.
 */
abstract class SearchApiProcessorTestBase extends EntityUnitTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  public static $modules = array('user', 'node', 'search_api','search_api_db', 'search_api_test_backend', 'comment', 'entity_reference');

  /**
   * The processor used for these tests.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * @var \Drupal\search_api\Entity\Index
   */
  protected $index;

  /**
   * @var \Drupal\search_api\Entity\Server
   */
  protected $server;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp($processor = NULL) {
    parent::setUp();

    $this->installSchema('node', array('node', 'node_field_data', 'node_field_revision', 'node_revision', 'node_access'));
    $this->installSchema('comment', array('comment'));
    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    $server_name = $this->randomName();
    $this->server = entity_create('search_api_server', array(
      'machine_name' => strtolower($server_name),
      'name' => $server_name,
      'status' => TRUE,
      'backendPluginId' => 'search_api_db',
      'backendPluginConfig' => array(
        'min_chars' => 3,
        'database' => 'default:default',
      ),
    ));
    $this->server->save();

    $index_name = $this->randomName();
    $this->index = entity_create('search_api_index', array(
      'machine_name' => strtolower($index_name),
      'name' => $index_name,
      'status' => TRUE,
      'datasourcePluginIds' => array('entity:comment', 'entity:node'),
      'serverMachineName' => $server_name,
      'trackerPluginId' => 'default_tracker',
    ));
    $this->index->setServer($this->server);
    $this->index->setOption('fields', array(
      'entity:comment|subject' => array(
        'type' => 'text',
      ),
      'entity:comment|status' => array(
        'type' => 'boolean',
      ),
      'entity:node|title' => array(
        'type' => 'text',
      ),
      'entity:node|author' => array(
        'type' => 'integer',
      ),
      'entity:node|status' => array(
        'type' => 'boolean',
      ),
    ));
    if ($processor) {
      $this->index->setOption('processors', array(
        $processor => array(
          'status' => TRUE,
          'weight' => 0,
        ),
      ));
    }
    $this->index->save();

    /** @var \Drupal\search_api\Processor\ProcessorPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.search_api.processor');
    $this->processor = $plugin_manager->createInstance($processor, array('index' => $this->index));
  }

  /**
   * Populates testing items.
   *
   * @param array $items
   *   Data to populate test items.
   *    - datasource: The datasource plugin id.
   *    - item: The item object to be indexed.
   *    - item_id: Unique item id.
   *    - text: Textual value of the test field.
   *
   * @return array
   *   An array structure as defined by BackendSpecificInterface::indexItems().
   */
  public function generateItems(array $items) {
    // Convert index "fields" option to datasource-specific arrays suitable for
    // indexing.
    $fields = array();
    foreach ($this->index->getOption('fields', array()) as $key => $field) {
      $field['original_type'] = "field_item:{$field['type']}";
      $field['value'] = array();
      if (strpos($key, IndexInterface::DATASOURCE_ID_SEPARATOR)) {
        list ($datasource_id, $property_path) = explode(IndexInterface::DATASOURCE_ID_SEPARATOR, $key, 2);
        $fields[$datasource_id][$property_path] = $field;
      }
      else {
        $fields[NULL][$key] = $field;
      }
    }

    $extracted_items = array();
    foreach ($items as $item) {
      $id = $item['datasource'] . '|' . $item['item_id'];
      $extracted_items[$id] = array(
        '#item' => $item['item'],
        '#datasource' => $item['datasource'],
        '#item_id' => $item['item_id'],
      );
      foreach (array(NULL, $item['datasource']) as $datasource_id) {
        if (empty($fields[$datasource_id])) {
          continue;
        }
        foreach ($fields[$datasource_id] as $key => $field) {
          if (isset($item[$key])) {
            $field['value'][] = $item[$key];
          }
          if ($datasource_id) {
            $key = $datasource_id . IndexInterface::DATASOURCE_ID_SEPARATOR . $key;
          }
          $extracted_items[$id][$key] = $field;
        }
      }
    }

    return $extracted_items;
  }

}
