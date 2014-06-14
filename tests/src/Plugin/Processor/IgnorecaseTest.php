<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase;
use Drupal\search_api\Tests\Processor\TestItemsTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\Component\Utility\Unicode;

/**
 * Tests the "Ignore case" processor plugin.
 *
 * @group Drupal
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase
 */
class IgnoreCaseTest extends UnitTestCase {

  use TestItemsTrait;

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase
   */
  protected $processor;

  /**
   * The test items to use for testing.
   *
   * @var \Drupal\search_api\Item\ItemInterface[]
   */
  protected $items;

  /**
   * The field ID of the fulltext field used in the tests.
   *
   * @var string
   */
  protected $fulltext_field_id;

  /**
   * The field ID of the string field used in the tests.
   *
   * @var string
   */
  protected $string_field_id;

  /**
   * The expected field value for unprocessed fields.
   *
   * @var string
   */
  protected $unprocessed_value = 'Foo bar BaZ, ÄÖÜÀÁ<>»«.';

  /**
   * The expected field value for processed fields.
   *
   * @var string
   */
  protected $processed_value;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Ignore case Processor Plugin',
      'description' => 'Unit test of processor ignores case of strings.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $this->processor = new IgnoreCase(array(), 'ignorecase', array());

    $this->fulltext_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'field_name';
    $this->string_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'field_mail';

    $this->processed_value = Unicode::strtolower($this->unprocessed_value);
    $fields = array(
      $this->fulltext_field_id => array(
        'type' => 'text',
        'values' => array($this->unprocessed_value),
      ),
      $this->string_field_id => array(
        'type' => 'string',
        'values' => array($this->unprocessed_value),
      ),
    );
    $this->items = $this->createItems($index, 1, $fields);
  }

  /**
   * Tests preprocessing of fulltext fields.
   */
  public function testPreprocessFulltextFields() {
    $configuration['fields'][$this->fulltext_field_id] = $this->fulltext_field_id;
    $this->processor->setConfiguration($configuration);

    $this->processor->preprocessIndexItems($this->items);

    $this->assertEquals($this->items[$this->item_ids[0]]->getField($this->fulltext_field_id)->getValues(), array($this->processed_value), 'Name field was correctly processed.');
    $this->assertEquals($this->items[$this->item_ids[0]]->getField($this->string_field_id)->getValues(), array($this->unprocessed_value), 'Mail field was not processed.');
  }

  /**
   * Tests preprocessing of tokenized fulltext fields.
   */
  public function testPreprocessTokenizedFulltextFields() {
    $configuration['fields'][$this->fulltext_field_id] = $this->fulltext_field_id;
    $this->processor->setConfiguration($configuration);

    $tokenize = function ($value) {
      return array('value' => $value, 'score' => 1);
    };
    $tokenized_value = array_map($tokenize, explode(' ', $this->unprocessed_value));
    $this->processed_value = array_map($tokenize, explode(' ', $this->processed_value));
    $field = $this->items[$this->item_ids[0]]->getField($this->fulltext_field_id);
    $field->setValues(array($tokenized_value));
    $field->setType('tokenized_text');

    $this->processor->preprocessIndexItems($this->items);

    $this->assertEquals(array($this->processed_value), $this->items[$this->item_ids[0]]->getField($this->fulltext_field_id)->getValues(), 'Tokenized Name field was correctly processed.');
    $this->assertEquals(array($this->unprocessed_value), $this->items[$this->item_ids[0]]->getField($this->string_field_id)->getValues(), 'Mail field was not processed.');
  }

  /**
   * Tests preprocessing of string fields.
   */
  public function testPreprocessStringFields() {
    $configuration['fields'][$this->string_field_id] = $this->string_field_id;
    $this->processor->setConfiguration($configuration);

    $this->processor->preprocessIndexItems($this->items);

    $this->assertEquals(array($this->unprocessed_value), $this->items[$this->item_ids[0]]->getField($this->fulltext_field_id)->getValues(), 'Name field was not processed.');
    $this->assertEquals(array($this->processed_value), $this->items[$this->item_ids[0]]->getField($this->string_field_id)->getValues(), 'Mail field was correctly processed.');
  }

}
