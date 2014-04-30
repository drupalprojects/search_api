<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiProcessorTestBase.
 */

namespace Drupal\search_api\Tests;

use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Search API Processor tests base class.
 */
abstract class SearchApiProcessorTestBase extends EntityUnitTestBase {

  /**
   * The processor used for these tests.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp($processor = NULL) {
    parent::setUp();

    $name = $this->randomName();
    $index = entity_create('search_api_index', array(
      'machine_name' => strtolower($name),
      'name' => $name,
      'status' => TRUE,
      'datasourcePluginIds' => array('entity:comment')
    ));
    $index->save();

    /** @var \Drupal\search_api\Processor\ProcessorPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.search_api.processor');
    $this->processor = $plugin_manager->createInstance($processor, array('index' => $index));
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
    $extracted_items = array();
    foreach ($items as $item) {
      $extracted_items[$item['datasource'] . '|' . $item['item_id']] = array(
        '#item' => $item['item'],
        '#datasource' => $item['datasource'],
        '#item_id' => $item['item_id'],
        'id' => array(
          'type' => 'integer',
          'original_type' => 'field_item:integer',
          'value' => array($item['item_id']),
        ),
        'field_text' => array(
          'type' => 'text',
          'original_type' => 'field_item:string',
          'value' => array($item['text']),
        ),
      );
    }

    return $extracted_items;
  }

}
