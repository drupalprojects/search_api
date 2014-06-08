<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\HtmlFilterTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the HtmlFilter processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class HtmlFilterTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'HTML filter processor test',
      'description' => 'Test if HTML Filter processor works.',
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
   * @dataProvider titleConfigurationDataProvider
   */
  public function testTitleConfiguration($passedString, array $expectedValue, $titleConfig) {
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue'),
      array(array('tags' => "", 'title' => $titleConfig, 'alt' => 0), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));

    $this->assertEquals($passedString, $expectedValue);

  }

  /**
   * Data provider for testTitleConfiguration().
   */
  public function titleConfigurationDataProvider() {
    return array(
      array('word', array(array('value' => 'word', 'score' => 1)), 0),
      array('word', array(array('value' => 'word', 'score' => 1)), 1),
      array('<div>word</div>', array(array('value' => 'word', 'score' => 1)), 1),
      array('<div title="TITLE">word</div>', array(array('value' => 'TITLE word', 'score' => 1)), 1),
      array('<div title="TITLE">word</div>', array(array('value' => 'word', 'score' => 1)), 0),
      array('<div data-title="TITLE">word</div>', array(array('value' => 'word', 'score' => 1)), 1),
      array('<div title="TITLE">word</a>', array(array('value' => 'TITLE word', 'score' => 1)), 1),
    );
  }

  /**
   * Test processFieldValue method with alt fetching enabled.
   * The arguments are being filled by the altConfigurationDataProvider
   *
   * @dataProvider altConfigurationDataProvider
   */
  public function testAltConfiguration($passedString, array $expectedValue, $altBoost) {
    // Mock the HTMLFilter class and fetch the two methods we want to test.
    //Initialize them with the default values as given in the arguments.
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue'),
      array(array('tags' => array('img' => '2'), 'title' => 0, 'alt' => $altBoost), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));

    $this->assertEquals($passedString, $expectedValue);

  }

  /**
   * Data provider method for testAltConfiguration()
   */
  public function altConfigurationDataProvider() {
    return array(
      array('word', array(array('value' => 'word', 'score' => 1)), 0),
      array('word', array(array('value' => 'word', 'score' => 1)), 1),
      array('<img src"href">word', array(array('value' => "word", 'score' => 1)), 1),
      array('<img alt="ALT"> word', array(array('value' => "ALT word", 'score' => 1)), 1),
      array('<img alt="ALT"> word', array(array('value' => "word", 'score' => 1)), 0),
      array('<img data-alt="ALT"> word', array(array('value' => "word", 'score' => 1)), 1),
      array('<img alt="ALT"> word </a>', array(array('value' => "ALT word", 'score' => 1)), 1),
    );
  }

  /**
   * Test processFieldValue method with tag provided fetching enabled.
   *
   * @dataProvider tagConfigurationDataProvider
   */
  public function testTagConfiguration($passedString, array $expectedValue, $tagsConfig) {
    $htmlFilterMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\HTMLFilter',
      array('processFieldValue'),
      array(array('tags' => $tagsConfig, 'title' => 0, 'alt' => 0), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($htmlFilterMock, array(&$passedString));
    $this->assertEquals($passedString, $expectedValue);

  }

  /**
   * Data provider method for testTagConfiguration()
   *
   * @todo add some more cases.
   */
  public function tagConfigurationDataProvider() {
    return array(
      array('h2word', array(array('value' => 'h2word', 'score' => '1')), ''),
      array('h2word', array(array('value' => 'h2word', 'score' => '1')), array('h2' => '2')),
      array('<h2> h2word </h2>', array(array('value' => 'h2word', 'score' => '2'), array('value' => 'h2word', 'score' => '1')), array('h2' => '2')),
    );
  }

  /**
   * Test getValueAndScoreFromHTML method.
   *
   * @dataProvider getValueAndScoreFromHTMLDataProvider
   */
  public function testGetValueAndScoreFromHTMLMethod($value, array $expectedValue, $tagsString) {

    $configuration = array('tags' => $tagsString);
    $plugin_id = 'Test';
    $plugin_definition = array();
    $htmlFilter = new HTMLFilter($configuration, $plugin_id, $plugin_definition);
    $processFieldValueMethod = $this->getAccessibleMethod('getValueAndScoreFromHTML');
    $result = $processFieldValueMethod->invokeArgs($htmlFilter, array($value));
    $this->assertEquals($result, $expectedValue);
  }

  /**
   * Data provider for testGetValueAndScoreFromHTMLMethod.
   *
   * @todo add other cases.
   */
  public function getValueAndScoreFromHTMLDataProvider() {
    return array(
      array('<div>word</div>', array('div' => array('value' => 'word', 'score' => 2)), array('div' => '2')),
    );
  }

}
