<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TransliterationTest.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Transliteration;

/**
 * Tests the Transliteration processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class TransliterationTest extends ProcessorTestBase {

  use TestItemsTrait;

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
    parent::setUp('transliteration');
    $this->processor = new Transliteration(array(), 'transliteration', array());
  }

  /**
   * Tests that integers are not affected.
   */
  public function testTransliterationWithInteger() {
    $field_value = 5;
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'int', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual($field->getValues(), array($field_value), 'Integer not affected by transliteration.');
  }

  /**
   * Tests that floating point numbers are not affected.
   */
  public function testTransliterationWithDouble() {
    $field_value = 3.14;
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'double', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual($field->getValues(), array($field_value), 'Floating point number not affected by transliteration.');
  }

  /**
   * Tests that ASCII strings are not affected.
   */
  public function testTransliterationWithUSAscii() {
    $field_value = 'ABCDEfghijk12345/$*';
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'string', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual($field->getValues(), array($field_value), 'ASCII strings are not affected by transliteration.');
  }

  /**
   * Tests correct transliteration of umlaut and accented characters.
   */
  public function testTransliterationWithNonUSAscii() {
    $field_value = 'Größe à férfi';
    $transliterated_value = 'Grosse a ferfi';
    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $items = $this->createSingleFieldItem($this->index, 'string', $field_value, $field);
    $this->processor->preprocessIndexItems($items);
    $this->assertEqual($field->getValues(), array($transliterated_value), 'Umlauts and accented characters are properly transliterated.');
  }

}
