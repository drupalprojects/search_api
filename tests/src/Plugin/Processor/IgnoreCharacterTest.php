<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Component\Utility\String;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Ignore Character" processor plugin.
 *
 * @group Drupal
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter
 */
class IgnoreCharacterTest extends UnitTestCase {

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
   * Get an accessible method of TokenizerTest using reflection.
   */
  public function getAccessibleMethod($methodName) {
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Test processFieldValue method with title fetching enabled.
   *
   * @dataProvider textDataProvider
   */
  public function testStringIgnoreCharacter($passedString, $expectedValue) {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter',
      array('processFieldValue'),
      array(array('strip' => array('ignorable' => "[']", 'character_sets' => array('Pc' => 'Pc'))), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    // Decode entities to UTF-8
    $passedString = String::decodeEntities($passedString);
    $processFieldValueMethod->invokeArgs($tokenizerMock, array(&$passedString, 'text'));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Data provider for testValueConfiguration().
   */
  public function textDataProvider() {
    return array(
      array('word¡!', 'word'),
      array('word\'s', 'words'),
      array('word_s', 'words'),
    );
  }
}
