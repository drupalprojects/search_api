<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase;
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
  }

  /**
   * Test processFieldValue method fot the ignoreCaseProcessor. (text)
   *
   * @dataProvider ignoreCaseDataProvider
   */
  public function testIgnoreCaseText($passedString, $expectedValue) {
    $ignoreCaseFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase',
      array('processFieldValue'),
      array(array(), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($ignoreCaseFilterMock, array(&$passedString, 'text'));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Test processFieldValue method fot the ignoreCaseProcessor. (string)
   *
   * @dataProvider ignoreCaseDataProvider
   */
  public function testIgnoreCaseString($passedString, $expectedValue) {
    $ignoreCaseFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase',
      array('processFieldValue'),
      array(array(), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($ignoreCaseFilterMock, array(&$passedString, 'string'));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Data provider method for testIgnoreCaseText() and testIgnoreCaseString()
   */
  public function ignoreCaseDataProvider() {
    return array(
      array('Foo bar', 'foo bar'),
      array('foo Bar', 'foo bar'),
      array('Foo Bar', 'foo bar'),
      array('Foo bar BaZ, ÄÖÜÀÁ<>»«.', 'foo bar baz, äöüàá<>»«.')
    );
  }

  /**
   * Get an accessible method of the processor class using reflection.
   */
  public function getAccessibleMethod($methodName) {
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

}
