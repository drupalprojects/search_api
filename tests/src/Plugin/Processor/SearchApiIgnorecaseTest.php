<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiIgnorecaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Language\Language;
use Drupal\Component\Utility\Unicode;

/**
 * Tests the Ignorecase processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiIgnorecaseTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase
   */
  protected $processor;

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

    $this->processor = new Ignorecase(array(), 'search_api_ignorecase_processor', array());
  }

  /**
   * Test ignorecase method AND processor configuration.
   *
   * @todo Two tests. One for the processor configuration; and one for the
   *   ignore case processor - using reflection to access the protected method.
   */
  public function testIgnorecase() {
    $orig = 'Foo bar BaZ, ÄÖÜÀÁ<>»«.';
    $expected = Unicode::strtolower($orig);

    $items = array();
    $items['entity:node|1:en']['entity:node|field_name'] = $this->createStubItem('entity:node|field_name', $orig, 'text');
    $items['entity:node|1:en']['entity:node|field_mail'] = $this->createStubItem('entity:node|field_mail', $orig, 'string');
    $items['entity:node|1:en']['entity:node|search_api_language'] = $this->createStubItem('', Language::LANGCODE_NOT_SPECIFIED, 'string');

    $tmp = $items;
    $this->processor->setConfiguration(array('fields' => array('entity:node|field_name' => 'entity:node|field_name')));
    $this->processor->preprocessIndexItems($tmp);
    $this->assertEquals($tmp["entity:node|1:en"]["entity:node|field_name"]['value'][0], $expected, 'Name field was processed.');
    $this->assertEquals($tmp["entity:node|1:en"]["entity:node|field_mail"]['value'][0], $orig, "Mail field wasn't procesed.");

    $tmp = $items;
    $this->processor->setConfiguration(array('fields' => array('entity:node|field_name' => 'entity:node|field_name', 'entity:node|field_mail' => 'entity:node|field_mail')));
    $this->processor->preprocessIndexItems($tmp);
    $this->assertEquals($tmp["entity:node|1:en"]["entity:node|field_name"]['value'][0], $expected, 'Name field was processed.');
    $this->assertEquals($tmp["entity:node|1:en"]["entity:node|field_mail"]['value'][0], $expected, 'Mail field was processed.');
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
  protected function createStubItem($field_name, $field_value, $field_type) {
    $item = array(
      'type' => $field_type,
      'value' => array(
        "0" => $field_value,
      ),
      'original_type' => 'field_item:' . $field_type,
    );
    return $item;
  }
}
