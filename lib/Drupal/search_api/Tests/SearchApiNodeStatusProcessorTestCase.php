<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiNodeStatusProcessorTestCase
 */
namespace Drupal\search_api\Tests;

use \Drupal\search_api\Plugin\SearchApi\Processor;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the NodeStatus Processor Plugin
 */
class SearchApiNodeStatusProcessorTestCase extends DrupalUnitTestBase {

  /**
   * Modules to enabled.
   *
   * @var array
   */
  public static $modules = array('search_api');

  public static function getInfo() {
    return array(
      'name' => 'Tests NodeStatus Processor Plugin',
      'description' => 'Tests that the node processor plugin does as it should',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests
   */
  protected function setUp() {
    parent::setUp();
    $this->node_status_processor = new \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus(array(), 'search_api_node_status_processor', array());
  }

  /**
   *  Test that items with status set to NULL are removed
   */
  public function testNodeStatusWithNullStatus() {
    $items = $this->generateItems(50);
    for ($i = 0; $i < count($items); $i++) {
      $items[$i]->status = NULL;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 0);
  }

  /**
   *  Test that items with status set to 0 are removed
   */
  public function testNodeStatusWithZeroStatus() {
    $items = $this->generateItems(50);
    for ($i = 0; $i < count($items); $i++) {
      $items[$i]->status = 0;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 0);
  }

  /**
   *  Test that items with status set to FALSE are removed
   */
  public function testNodeStatusWithFalseStatus() {
    $items = $this->generateItems(50);
    for ($i = 0; $i < count($items); $i++) {
      $items[$i]->status = false;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 0);
  }

  /**
   *  Test that items with status set to 1 are not removed
   */
  public function testNodeStatusWithOneStatus() {
    $items = $this->generateItems(50);
    for ($i = 0; $i < count($items); $i++) {
      $items[$i]->status = 1;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 50);
  }

  /**
   *  Test that items with status set to TRUE are not removed
   */
  public function testNodeStatusWithTrueStatus() {
    $items = $this->generateItems(50);
    for ($i = 0; $i < count($items); $i++) {
      $items[$i]->status = true;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 50);
  }

  /**
   *  Test that items with status set to mixed values
   */
  public function testNodeStatusWithMixedStatus() {
    $items = $this->generateItems(50);
    $non_empty_count = 0;
    for ($i = 0; $i < count($items); $i++) {
      $value = rand(0,1);
      $non_empty_count += $value;
      $items[$i]->status = $value;
    }
    $this->node_status_processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == $non_empty_count);
  }

  /**
   * Generates $number_of_items items for use with
   * this test. status is set to 0, the test should
   * change this as appropriate.
   *
   * @param $number_of_items The number of items to create
   * @return array An array of items
   */
  protected function generateItems($number_of_items) {

    //for now we just need a stdclass with a status property
    $item = new \stdClass();
    $item->status = 0;

    $generated_items = array();
    for ($i = 0; $i < $number_of_items; $i++) {
      $generated_items[] = clone $item;
    }
    return $generated_items;
  }

}
