<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiAddURLTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Plugin\SearchApi\Processor\AddURL;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the AddURL processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiAddURLTest extends UnitTestCase {
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
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $items = array(
      'entity:node|1:en' => array(
        '#item' => $node,
        '#item_id' => '1:en',
        '#datasource' => 'entity:node',
        'search_api_language' => array(
          'type' => 'string',
          'value' => array(),
          'original_type' => NULL,
        ),
        'entity:node|body' => array(
          'type' => 'text',
          'value' => array('Some text value'),
          'original_type' => 'field_item:text_with_summary',
        ),
        'entity:node|search_api_url' => array(
          'type' => 'string',
          'value' => array(),
          'original_type' => NULL,
        ),
      ),
      'entity:node|2:en' => array(
        '#item' => $node,
        '#item_id' => '2:en',
        '#datasource' => 'entity:node',
        'entity:node|search_api_url' => array(
          'type' => 'string',
          'value' => array(),
          'original_type' => NULL,
        ),
      ),
    );

    // Process the items.
    $this->processor->preprocessIndexItems($items);

    // Check the valid item.
    $field = $items['entity:node|1:en']['entity:node|search_api_url'];
    $this->assertEquals(array('http://www.example.com/node/example'), $field['value'], 'Valid URL added as value to the field.');
    $this->assertEquals('uri', $field['original_type'], 'Type changed to "URI".');

    // Check that no other fields where changed.
    $field = $items['entity:node|1:en']['entity:node|body'];
    $this->assertEquals(array('Some text value'), $field['value'], 'Body field was not changed.');
    $this->assertEquals('field_item:text_with_summary', $field['original_type'], 'Type not changed.');

    // Check the second item to be sure that all are processed.
    $field = $items['entity:node|2:en']['entity:node|search_api_url'];
    $this->assertEquals(array('http://www.example.com/node/example'), $field['value'], 'Valid URL added as value to the field.');
    $this->assertEquals('uri', $field['original_type'], 'Type changed to "URI".');
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
    $this->assertTrue(array_key_exists('search_api_url', $properties), 'The Properties where modified with the "search_api_url".');
    $this->assertTrue(($properties['search_api_url'] instanceof DataDefinition), 'The "search_api_url" contains a valid DataDefinition instance.');
    $this->assertEquals('uri', $properties['search_api_url']->getDataType(), 'Valid DataType set in the DataDefinition.');
    $this->assertEquals('URI', $properties['search_api_url']->getLabel(), 'Valid Label set in the DataDefinition.');
    $this->assertEquals('A URI where the item can be accessed.', $properties['search_api_url']->getDescription(), 'Valid Description set in the DataDefinition.');
  }
}
