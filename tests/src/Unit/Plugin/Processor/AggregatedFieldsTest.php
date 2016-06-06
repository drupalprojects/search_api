<?php

namespace Drupal\Tests\search_api\Unit\Plugin\Processor;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Plugin\search_api\processor\AggregatedFields;
use Drupal\search_api\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Aggregated fields" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AggregatedField
 */
class AggregatedFieldsTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * The processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\AggregatedFields
   */
  protected $processor;

  /**
   * A search index mock for the tests.
   *
   * @var \Drupal\search_api\IndexInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $index;

  /**
   * The field ID used in this test.
   *
   * @var string
   */
  protected $fieldId = 'aggregated_field';

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->index = new Index(array(
      'datasourceInstances' => array(
        'entity:test1' => (object) array(),
      ),
      'processorInstances' => array(),
      'field_settings' => array(
        'foo' => array(
          'type' => 'string',
          'datasource_id' => 'entity:test1',
          'property_path' => 'foo',
        ),
        'bar' => array(
          'type' => 'string',
          'datasource_id' => 'entity:test1',
          'property_path' => 'foo:bar',
        ),
        'bla' => array(
          'type' => 'string',
          'datasource_id' => 'entity:test2',
          'property_path' => 'foobaz:bla',
        ),
        'aggregated_field' => array(
          'type' => 'string',
          'property_path' => 'aggregated_field',
        ),
      ),
      'properties' => array(
        NULL => array(),
        'entity:test1' => array(),
        'entity:test2' => array(),
      ),
    ), 'search_api_index');
    $this->processor = new AggregatedFields(array('index' => $this->index), 'aggregated_field', array());
    $this->index->addProcessor($this->processor);
    $this->setUpDataTypePlugin();
  }

  /**
   * Tests aggregated fields of the given type.
   *
   * @param string $type
   *   The aggregation type to test.
   * @param array $expected
   *   The expected values for the two items.
   * @param bool $integer
   *   (optional) TRUE if the items' normal fields should contain integers,
   *   FALSE otherwise.
   *
   * @dataProvider aggregationTestsDataProvider
   */
  public function testAggregation($type, $expected, $integer = FALSE) {
    // Add the field configuration.
    $configuration = array(
      'type' => $type,
      'fields' => array(
        'entity:test1/foo',
        'entity:test1/foo:bar',
        'entity:test2/foobaz:bla',
      ),
    );
    $this->index->getField($this->fieldId)->setConfiguration($configuration);

    if ($integer) {
      $field_values = array(
        'foo' => array(2, 4),
        'bar' => array(16),
        'bla' => array(7),
      );
    }
    else {
      $field_values = array(
        'foo' => array('foo', 'bar'),
        'bar' => array('baz'),
        'bla' => array('foobar'),
      );
    }
    $items = array();
    $i = 0;
    foreach (array('entity:test1', 'entity:test2') as $datasource_id) {
      $this->itemIds[$i++] = $item_id = Utility::createCombinedId($datasource_id, '1:en');
      $item = Utility::createItem($this->index, $item_id);
      foreach ($this->index->getFields() as $field_id => $field) {
        $field = clone $field;
        if (!empty($field_values[$field_id])) {
          $field->setValues($field_values[$field_id]);
        }
        $item->setField($field_id, $field);
      }
      $item->setFieldsExtracted(TRUE);
      $items[$item_id] = $item;
    }

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    $this->assertEquals($expected[0], $items[$this->itemIds[0]]->getField($this->fieldId)->getValues(), 'Correct aggregation for item 1.');
    $this->assertEquals($expected[1], $items[$this->itemIds[1]]->getField($this->fieldId)->getValues(), 'Correct aggregation for item 2.');
  }

  /**
   * Provides test data for aggregation tests.
   *
   * @return array
   *   An array containing test data sets, with each being an array of
   *   arguments to pass to the test method.
   *
   * @see static::testAggregation()
   */
  public function aggregationTestsDataProvider() {
    return array(
      '"Union" aggregation' => array(
        'union',
        array(
          array('foo', 'bar', 'baz'),
          array('foobar'),
        ),
      ),
      '"Concatenation" aggregation' => array(
        'concat',
        array(
          array("foo\n\nbar\n\nbaz"),
          array('foobar'),
        ),
      ),
      '"Sum" aggregation' => array(
        'sum',
        array(
          array(22),
          array(7),
        ),
        TRUE,
      ),
      '"Count" aggregation' => array(
        'count',
        array(
          array(3),
          array(1),
        ),
      ),
      '"Maximum" aggregation' => array(
        'max',
        array(
          array(16),
          array(7),
        ),
        TRUE,
      ),
      '"Minimum" aggregation' => array(
        'min',
        array(
          array(2),
          array(7),
        ),
        TRUE,
      ),
      '"First" aggregation' => array(
        'first',
        array(
          array('foo'),
          array('foobar'),
        ),
      ),
    );
  }

  /**
   * Tests whether the properties are correctly altered.
   *
   * @see \Drupal\search_api\Plugin\search_api\processor\AggregatedField::alterPropertyDefinitions()
   */
  public function testAlterPropertyDefinitions() {
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->processor->setStringTranslation($translation);

    // Check for added properties when no datasource is given.
    /** @var \Drupal\search_api\Processor\ProcessorPropertyInterface[] $properties */
    $properties = $this->processor->getPropertyDefinitions(NULL);

    $this->assertArrayHasKey('aggregated_field', $properties, 'The "aggregated_field" property was added to the properties.');
    $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty', $properties['aggregated_field'], 'The "aggregated_field" property has the correct class.');
    $this->assertEquals('string', $properties['aggregated_field']->getDataType(), 'Correct data type set in the data definition.');
    $this->assertEquals($translation->translate('Aggregated field'), $properties['aggregated_field']->getLabel(), 'Correct label set in the data definition.');
    $expected_description = $translation->translate('An aggregation of multiple other fields.');
    $this->assertEquals($expected_description, $properties['aggregated_field']->getDescription(), 'Correct description set in the data definition.');

    // Verify that there are no properties if a datasource is given.
    $datasource = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $properties = $this->processor->getPropertyDefinitions($datasource);
    $this->assertEmpty($properties, 'Datasource-specific properties did not get changed.');
  }

}
