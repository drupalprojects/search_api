<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiIgnorecaseTest.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Transliteration;

/**
 * Tests the Transliteration processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiTransliterationTest extends SearchApiProcessorTestBase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Transliteration
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Transliteration Processor Plugin',
      'description' => 'Tests of processor to do transliteration of characters.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp('search_api_transliteration_processor');
    $this->processor = new Transliteration(array(), 'search_api_transliteration_processor', array());
  }

  /**
   * Test that integers are not affected
   */
  public function testTransliterationWithInteger() {
    $field_value = 5;
    $field_type = 'int';

    $items = array();
    $items['entity:node|1:en']['entity:node|field_testing'] = $this->createStubItem($field_value, $field_type);
    $this->processor->preprocessIndexItems($items);
    // All fields are multivalued fields now
    $this->assertTrue($items["entity:node|1:en"]["entity:node|field_testing"]['value'][0] === $field_value);
  }

  /**
   * Test that integers are not affected
   */
  public function testTransliterationWithDouble() {
    $field_value = 3.14;
    $field_type = 'double';
    $items = array();
    $items['entity:node|1:en']['entity:node|field_testing'] = $this->createStubItem($field_value, $field_type);
    $this->processor->preprocessIndexItems($items);
    $this->assertTrue($items["entity:node|1:en"]["entity:node|field_testing"]['value'][0] === $field_value);
  }

  /**
   * Test that USAscii strings are not affected
   */
  public function testTransliterationWithUSAscii() {
    $field_value = "ABCDE12345";
    $field_type = 'string';
    $items = array();
    $items['entity:node|1:en']['entity:node|field_testing'] = $this->createStubItem($field_value, $field_type);
    $this->processor->preprocessIndexItems($items);
    $this->assertTrue($items["entity:node|1:en"]["entity:node|field_testing"]['value'][0] === $field_value);
  }

  /**
   * Test that non-USAscii strings are affected
   */
  public function testTransliterationWithNonUSAscii() {
    //this should come back as 'Grosse'
    $field_value = "Größe";
    $field_type = 'string';
    $items = array();
    $items['entity:node|1:en']['entity:node|field_testing'] = $this->createStubItem($field_value, $field_type);
    $this->processor->preprocessIndexItems($items);
    $this->assertFalse($items["entity:node|1:en"]["entity:node|field_testing"]['value'][0] === $field_value);
  }

  /**
   * createStubItem
   * Helper method to create an item for use with preprocessIndexItems
   *
   * @param mixed $field_name
   * @param mixed $field_value
   * @param mixed $field_type
   * @return array formatted for use with preProcessIndexItems
   */
  protected function createStubItem($field_value, $field_type) {
    return array(
      'type' => $field_type,
      'value' => array(
        "0" => $field_value,
      ),
      'original_type' => 'field_item:text',
    );
  }

}
