<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\NodeStatusTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Node Status processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class NodeStatusTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus
   */
  protected $processor;

  /**
   * The test items to use.
   *
   * @var \Drupal\search_api\Item\ItemInterface[]
   */
  protected $items = array();

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'NodeStatus Processor Plugin',
      'description' => 'Unit test of preprocessor for node status.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = new NodeStatus(array(), 'node_status', array());

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $datasource = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $datasource->expects($this->any())
      ->method('getEntityTypeId')
      ->will($this->returnValue('node'));
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */

    $item = Utility::createItem($index, 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '1:en', $datasource);
    $unpublished_node = $this->getMockBuilder('Drupal\search_api\Tests\TestNodeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $unpublished_node->expects($this->any())
      ->method('Ispublished')
      ->will($this->returnValue(FALSE));
    /** @var \Drupal\node\NodeInterface $unpublished_node */

    $item->setOriginalObject($unpublished_node);
    $this->items[$item->getId()] = $item;

    $item = Utility::createItem($index, 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '2:en', $datasource);
    $published_node = $this->getMockBuilder('Drupal\search_api\Tests\TestNodeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $published_node->expects($this->any())
      ->method('Ispublished')
      ->will($this->returnValue(TRUE));
    /** @var \Drupal\node\NodeInterface $published_node */

    $item->setOriginalObject($published_node);
    $this->items[$item->getId()] = $item;
  }

  /**
   * Tests is unpublished nodes are removed from the items list.
   */
  public function testNodeStatus() {
    $this->assertCount(2, $this->items, '2 nodes in the index.');
    $this->processor->preprocessIndexItems($this->items);

    $this->assertCount(1, $this->items, 'Unpublished node is removed from items list.');
  }
}
