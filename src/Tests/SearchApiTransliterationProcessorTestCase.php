<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiTransliterationProcessorTestCase
 */

namespace Drupal\search_api\Tests;

use \Drupal\search_api\Plugin\SearchApi\Processor;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Tests the Transliteration Processor Plugin
 */
class SearchApiTransliterationProcessorTestCase extends DrupalUnitTestBase {

  /**
   * Modules to enabled.
   *
   * @var array
   */
  public static $modules = array('search_api');

  public static function getInfo() {
    return array(
      'name' => 'Tests Transliteration Processor Plugin',
      'description' => 'Tests that the transliteration processor plugin does as it should',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests
   */
  protected function setUp() {
    parent::setUp();
    $this->transliteration_processor = new \Drupal\search_api\Plugin\SearchApi\Processor\Transliteration(array(), 'search_api_transliteration_processor', array());
  }

  /**
   * Test that integers are not affected
   */
  public function testTransliterationWithInteger() {
    $field_value = 5;
    $field_type = 'int';
    $items[] = $this->createItem($field_value, $field_type);
    $this->transliteration_processor->preprocessIndexItems($items);
    $this->assertTrue($items[0]['test_field']['value'] === $field_value);
  }

  /**
   * Test that integers are not affected
   */
  public function testTransliterationWithDouble() {
    $field_value = 3.14;
    $field_type = 'double';
    $items[] = $this->createItem($field_value, $field_type);
    $this->transliteration_processor->preprocessIndexItems($items);
    $this->assertTrue($items[0]['test_field']['value'] === $field_value);
  }

  /**
   * Test that USAscii strings are not affected
   */
  public function testTransliterationWithUSAscii() {
    $field_value = "ABCDE12345";
    $field_type = 'string';
    $items[] = $this->createItem($field_value, $field_type);
    $this->transliteration_processor->preprocessIndexItems($items);
    $this->assertTrue($items[0]['test_field']['value'] === $field_value);
  }

  /**
   * Test that non-USAscii strings are affected
   */
  public function testTransliterationWithNonUSAscii() {
    //this should come back as 'Grosse'
    $field_value = "Größe";
    $field_type = 'string';
    $items[] = $this->createItem($field_value, $field_type);
    $this->transliteration_processor->preprocessIndexItems($items);
    $this->assertFalse($items[0]['test_field']['value'] === $field_value);
  }

  /**
   * createItem
   * Helper method to create an item for use with preprocessIndexItems
   *
   * @param mixed $field_value
   * @param mixed $field_type
   * @return array formatted for use with preProcessIndexItems
   */
  protected function createItem($field_value, $field_type) {
    $field = array();
    $field['value'] = $field_value;
    $field['type'] = $field_type;
    $item = array();
    $item['test_field'] = $field;
    return $item;
  }

}
