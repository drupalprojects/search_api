<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TokenizerTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the Tokenizer processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class TokenizerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tokenizer processor test',
      'description' => 'Test if Tokenizer processor works.',
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
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Test processFieldValue method with title fetching enabled.
   *
   * @dataProvider textDataProvider
   */
  public function testStringTokenizing($passedString, $expectedValue) {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array(), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($tokenizerMock, array(&$passedString, 'text'));
    $this->assertEquals($passedString, $expectedValue);


  }

  /**
   * Data provider for testValueConfiguration().
   */
  public function textDataProvider() {
    return array(
      array('word', 'word'),
      array('word word', array(0 => array('value' => 'word'), 1 => array('value' => 'word'))),
      array('word\'s word', array(0 => array('value' => 'words'), 1 => array('value' => 'word'))),
    );
  }

}
