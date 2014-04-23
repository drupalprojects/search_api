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
    $processed = Unicode::strtolower($orig);
    $items = array(
      1 => array(
        'name' => array(
          'type' => 'text',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'mail' => array(
          'type' => 'string',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'search_api_language' => array(
          'type' => 'string',
          'original_type' => 'string',
          'value' => Language::LANGCODE_NOT_SPECIFIED,
        ),
      ),
    );
    $keys1 = $keys2 = array(
      'foo',
      'bar baz',
      'foobar1',
      '#conjunction' => 'AND',
    );
    $filters1 = array(
      array('name', 'foo', '='),
      array('mail', 'BAR', '='),
    );
    $filters2 = array(
      array('name', 'foo', '='),
      array('mail', 'bar', '='),
    );

    $tmp = $items;
    $this->processor->setConfiguration(array('fields' => array('name' => 'name')));
    $this->processor->preprocessIndexItems($tmp);
    $this->assertEquals($tmp[1]['name']['value'], $processed, 'Name field was processed.');
    $this->assertEquals($tmp[1]['mail']['value'], $orig, "Mail field wasn't procesed.");

    $tmp = $items;
    $this->processor->setConfiguration(array('fields' => array('name' => 'name', 'mail' => 'mail')));
    $this->processor->preprocessIndexItems($tmp);
    $this->assertEquals($tmp[1]['name']['value'], $processed, 'Name field was processed.');
    $this->assertEquals($tmp[1]['mail']['value'], $processed, 'Mail field was processed.');
  }

}
