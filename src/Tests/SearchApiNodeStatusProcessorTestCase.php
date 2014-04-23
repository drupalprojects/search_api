<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiNodeStatusProcessorTestCase
 */
namespace Drupal\search_api\Tests;

use Drupal\node\Entity\Node;
use \Drupal\search_api\Plugin\SearchApi\Processor;
use Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the NodeStatus Processor Plugin
 */
class SearchApiNodeStatusProcessorTestCase extends DrupalUnitTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  public static $modules = array('node', 'search_api');

  /**
   * The processor used for these tests.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests NodeStatus Processor Plugin',
      'description' => 'Tests that the node processor plugin does as it should',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new NodeStatus(array(), 'search_api_node_status_processor', array());
  }

  /**
   *  Test that items with status set to NULL are removed.
   */
  public function testNodeStatusWithNullStatus() {
    $items = $this->generateItems(50, NULL);
    $this->processor->preprocessIndexItems($items);
    $this->assertTrue(count($items) == 0);
  }

  /**
   * Tests that items with status set to 0 are removed.
   */
  public function testNodeStatusWithZeroStatus() {
    $items = $this->generateItems(50, 0);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual(count($items), 0);
  }

  /**
   * Tests that items with status set to FALSE are removed.
   */
  public function testNodeStatusWithFalseStatus() {
    $items = $this->generateItems(50, FALSE);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual(count($items), 0);
  }

  /**
   * Tests that items with status set to 1 are not removed.
   */
  public function testNodeStatusWithOneStatus() {
    $items = $this->generateItems(50, 1);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual(count($items), 50);
  }

  /**
   * Tests that items with status set to TRUE are not removed.
   */
  public function testNodeStatusWithTrueStatus() {
    $items = $this->generateItems(50, TRUE);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual(count($items), 50);
  }

  /**
   * Tests that items with status set to mixed values
   */
  public function testNodeStatusWithMixedStatus() {
    $items = $this->generateItems(50);
    $non_empty_count = 0;
    for ($i = 0; $i < count($items); $i++) {
      $value = rand(0,1);
      $non_empty_count += $value;
      /** @var \Drupal\node\NodeInterface $item */
      $item = $items[$i]['#item'];
      $item->setPublished($value);
    }
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual(count($items), $non_empty_count);
  }

  /**
   * Generates items for use with this test.
   *
   * @param int $number_of_items
   *   The number of items to create.
   * @param mixed $status
   *   The status to set for the nodes in the array.
   *
   * @return array
   *   An array of items to be indexed.
   */
  protected function generateItems($number_of_items, $status = NULL) {
    $item = new Node(array('status' => $status), 'node');
    $generated_items = array();
    for ($i = 0; $i < $number_of_items; $i++) {
      $generated_items[] = array(
        '#item' => clone $item,
      );
    }
    return $generated_items;
  }

}
