<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Ignore case" processor plugin.
 *
 * @group Drupal
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase
 */
class IgnoreCaseTest extends UnitTestCase {

  use ProcessorTestTrait;

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

    $this->processor = new IgnoreCase(array(), 'string', array());
  }

  /**
   * Tests the process() method.
   *
   * @dataProvider processDataProvider
   */
  public function testProcess($passedString, $expectedValue) {
    $this->invokeMethod('process', array(&$passedString));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Provides sets of arguments for testProcess().
   *
   * @return array[]
   *   Arrays of arguments for testProcess().
   */
  public function processDataProvider() {
    return array(
      array('Foo bar', 'foo bar'),
      array('foo Bar', 'foo bar'),
      array('Foo Bar', 'foo bar'),
      array('Foo bar BaZ, ÄÖÜÀÁ<>»«.', 'foo bar baz, äöüàá<>»«.')
    );
  }

}
