<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Processors\HtmlFilterProcessorTest.
 */

namespace Drupal\search_api\Tests\Processors;

use Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the HtmlFilter processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class HtmlFilterProcessorTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'HTML filter processor test',
      'description' => 'Test if HTML Filter processor works well.',
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
   * Get an accessible method of HTMLFilter using reflection.
   */
  public function getAccessibleMethod($methodName) {
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Test processFieldValue method with title fetching enabled.
   *
   * @dataProvider titleConfigurationDataProviding
   */
  public function testTitleConfiguration($passedString, $expectedValue, $titleConfig) {
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue', 'parseText'),
      array(array('tags' => "div: 2", 'title' => $titleConfig, 'alt' => 0), 'string', array()));

    $htmlFilterMock->expects($this->once())
      ->method('parseText')
      ->with($this->equalTo($expectedValue));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));
  }

  /**
   * Data provider for testTitleConfiguration().
   */
  public function titleConfigurationDataProviding() {
    return array(
      array('word', 'word', 0),
      array('word', 'word', 1),
      array('<div>word</div>', '<div> word </div>', 1),
      array('<div title="TITLE"> word </div>', 'TITLE <div title="TITLE"> word </div>', 1),
      array('<div title="TITLE"> word </div>', '<div title="TITLE"> word </div>', 0),
      array('<div data-title="TITLE"> word </div>', '<div data-title="TITLE"> word </div>', 1),
      array("<div title='TITLE'> word </div>", "TITLE <div title='TITLE'> word </div>", 1),
      array('<div title="TITLE"> word </a>', 'TITLE <div title="TITLE"> word', 1),
    );
  }

  /**
   * Test processFieldValue method with alt fetching enabled.
   *
   * @dataProvider altConfigurationDataProviding
   */
  public function testAltConfiguration($passedString, $expectedValue, $altConfig) {
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue', 'parseText'),
      array(array('tags' => "img: 2", 'title' => 0, 'alt' => $altConfig), 'string', array()));

    $htmlFilterMock->expects($this->once())
      ->method('parseText')
      ->with($this->equalTo($expectedValue));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));
  }

  /**
   * Data provider method for testAltConfiguration()
   */
  public function altConfigurationDataProviding() {
    return array(
      array('word', 'word', 0),
      array('word', 'word', 1),
      array('<img src"href">word', '<img src"href"> word', 1),
      array('<img alt="ALT"> word', 'ALT <img alt="ALT"> word', 1),
      array('<img alt="ALT"> word', '<img alt="ALT"> word', 0),
      array('<img data-alt="ALT"> word', '<img data-alt="ALT"> word', 1),
      array("<img alt='ALT'> word", "ALT <img alt='ALT'> word", 1),
      array('<img alt="ALT"> word </a>', 'ALT <img alt="ALT"> word', 1),
    );
  }

  /**
   * Test processFieldValue method with tag provided fetching enabled.
   *
   * @dataProvider tagConfigurationDataProviding
   */
  public function testTagConfiguration($passedString, $expectedValue, $tagsConfig) {
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue', 'parseText'),
      array(array('tags' => $tagsConfig, 'title' => 0, 'alt' => 0), 'string', array()));

    if (!empty($tagsConfig)) {
      $htmlFilterMock
        ->expects($this->once())
        ->method('parseText')
        ->with($this->equalTo($expectedValue));
    }
    else {
      $htmlFilterMock
        ->expects($this->exactly(0))
        ->method('parseText');
    }

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));
  }

  /**
   * Data provider method for testTagConfiguration()
   *
   * @todo add some more cases.
   */
  public function tagConfigurationDataProviding() {
    return array(
      array('word', '', ''),
      array('word', 'word', "h2: 2"),
      array('<h2>word</h2>', '<h2> word </h2>', "h2: 2"),
    );
  }

  /**
   * Test parseText method.
   *
   * @dataProvider parseTextDataProvider
   */
  public function testParseTextMethod($value, $tagsString, $expected) {
    $htmlFilter = new HTMLFilter(array('tags' => $tagsString), 'string', array());

    $processFieldValueMethod = $this->getAccessibleMethod('parseText');
    $result = $processFieldValueMethod->invokeArgs($htmlFilter, array(&$value));

    $this->assertEquals($result, $expected);
  }

  /**
   * Data provider for testParseTextMethod.
   *
   * @todo add other cases.
   */
  public function parseTextDataProvider() {
    return array(
      array('word', 'div: 2', array(array('value' => 'word', 'score' => 1))),
    );
  }

}
