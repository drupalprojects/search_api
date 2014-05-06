<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiStopwordsTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Core\Language\Language;
use Drupal\search_api\Plugin\SearchApi\Processor\RoleFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Role Filter processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiRoleFilterTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\RoleFilter
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'RoleFilter Processor Plugin',
      'description' => 'Unit test of preprocessor for role filter.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new RoleFilter(array(), 'search_api_role_filter_processor', array());;
  }

  /**
   * Test processing of index items process.
   */
  public function testProcessIndexItems() {

    $items = array(
      'entity:node|1' => array(
        '#datasource' => 'entity:node',
        '#item_id' => 1,
      ),
    );
    $items['entity:node|1']['#item'] = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $items['entity:user|2'] = array(
      '#datasource' => 'entity:user',
      '#item_id' => 2,
    );
    $items['entity:user|2']['#item'] = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();
    $items['entity:user|2']['#item']->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array('authenticated' => 'authenticated', 'editor' => 'editor')));

    $items['entity:user|3'] = array(
      '#datasource' => 'entity:user',
      '#item_id' => 3,
    );
    $items['entity:user|3']['#item'] = $this->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();
    $items['entity:user|3']['#item']->expects($this->any())
      ->method('getRoles')
      ->will($this->returnValue(array('authenticated' => 'authenticated')));

    // Create Index mock.
    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    // Create datasource mocks.
    $node = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $node->expects($this->any())
      ->method('getEntityTypeId')
      ->will($this->returnValue('node'));

    $user = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $user->expects($this->any())
      ->method('getEntityTypeId')
      ->will($this->returnValue('user'));

    // Add datasources to index.
    $index->expects($this->any())
      ->method('getDataSource')
      ->will($this->returnValueMap(
        array(
          array('entity:node', $node),
          array('entity:user', $user),
        )
      ));

    // Set the mocked index into the processor.
    $this->processor->setIndex($index);

    // Scenario 1 - users without editor role.
    $configuration = $this->processor->getConfiguration();
    $configuration['roles'] = array('editor' => 'editor');
    $configuration['default'] = 1;
    $this->processor->setConfiguration($configuration);

    $items_to_process = $items;
    $this->processor->preprocessIndexItems($items_to_process);

    $this->assertTrue(empty($items_to_process['entity:user|2']), 'User 2 with the editor role should have been removed');
    $this->assertTrue(!empty($items_to_process['entity:user|3']), 'User 3 without the editor role should have remained in the items list');
    $this->assertTrue(!empty($items_to_process['entity:node|1']), 'Node item should have stayed intact');

    // Scenario 2 - all users with authenticated role.
    $configuration = $this->processor->getConfiguration();
    $configuration['roles'] = array('authenticated' => 'authenticated');
    $configuration['default'] = 0;
    $this->processor->setConfiguration($configuration);

    $items_to_process = $items;
    $this->processor->preprocessIndexItems($items_to_process);

    $this->assertTrue(!empty($items_to_process['entity:user|2']), 'User 2 with both roles should have remained in the items list');
    $this->assertTrue(!empty($items_to_process['entity:user|3']), 'User 3 with the authenticated role should have remained in the list.');
    $this->assertTrue(!empty($items_to_process['entity:node|1']), 'Node item should have stayed intact');

  }

}
