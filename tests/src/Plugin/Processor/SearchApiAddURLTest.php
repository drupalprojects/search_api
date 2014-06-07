<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiAddURLTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Plugin\SearchApi\Processor\AddURL;
use Drupal\search_api\Tests\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the AddURL processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiAddURLTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\AddURL
   */
  protected $processor;

  /**
   * Index mock.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'AddURL Processor Plugin',
      'description' => 'Unit tests of postprocessor excerpt add urls.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    // Create a mock for the URL to be returned.
    $url = $this->getMockBuilder('Drupal\Core\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url->expects($this->any())
      ->method('toString')
      ->will($this->returnValue('http://www.example.com/node/example'));

    // Mock the data source of the indexer to return the mocked url object.
    $datasource = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $datasource->expects($this->any())
      ->method('getItemUrl')
      ->withAnyParameters()
      ->will($this->returnValue($url));

    // Create a mock for the indexer to get the dataSource object which holds the URL.
    $this->index = $this->getMock('Drupal\search_api\Index\IndexInterface');
    $this->index->expects($this->any())
      ->method('getDatasource')
      ->with('entity:node')
      ->will($this->returnValue($datasource));

    // Create the URL-Processor and set the mocked indexer.
    $this->processor = new AddURL(array(), 'search_api_add_url_processor', array());
    $this->processor->setIndex($this->index);
    $this->processor->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests processIndexItems.
   *
   * Check if the items are processed as expected.
   */
  public function testProcessIndexItems() {
    // @todo Why Node, not NodeInterface? Normally, you mock an interface.
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array('Some text value');
    $fields = array(
      'search_api_language' => array('type' => 'string'),
      'entity:node|body' => array('type' => 'text', 'values' => $body_value),
    );
    $items = $this->createItems($this->index, 2, $fields, $node);

    // Process the items.
    $this->processor->preprocessIndexItems($items);

    // Check the valid item.
    $field = $items['entity:node|1:en']->getField('search_api_url');
    $this->assertEquals(array('http://www.example.com/node/example'), $field->getValues(), 'Valid URL added as value to the field.');

    // Check that no other fields where changed.
    $field = $items['entity:node|1:en']->getField('entity:node|body');
    $this->assertEquals($body_value, $field->getValues(), 'Body field was not changed.');

    // Check the second item to be sure that all are processed.
    $field = $items['entity:node|2:en']->getField('search_api_url');
    $this->assertEquals(array('http://www.example.com/node/example'), $field->getValues(), 'Valid URL added as value to the field in the second item.');
  }

  /**
   * Tests alterPropertyDefinitions.
   *
   * Checks for the correct DataDefinition added to the properties.
   */
  public function testAlterPropertyDefinitions() {
    $properties = array();

    // Check for modified properties when no DataSource is given.
    $this->processor->alterPropertyDefinitions($properties, NULL);
    $property_added = array_key_exists('search_api_url', $properties);
    $this->assertTrue($property_added, 'The "search_api_url" property was added to the properties.');
    if ($property_added) {
      $this->assertTrue($properties['search_api_url'] instanceof DataDefinition, 'The "search_api_url" property contains a valid DataDefinition instance.');
      if ($properties['search_api_url'] instanceof DataDefinition) {
        $this->assertEquals('uri', $properties['search_api_url']->getDataType(), 'Correct data type set in the DataDefinition.');
        $this->assertEquals('URI', $properties['search_api_url']->getLabel(), 'Correct label set in the DataDefinition.');
        $this->assertEquals('A URI where the item can be accessed.', $properties['search_api_url']->getDescription(), 'Correct description set in the DataDefinition.');
      }
    }
  }
}
