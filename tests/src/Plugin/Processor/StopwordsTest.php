<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\StopwordsTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Stopwords;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Stopwords processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class StopwordsTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Stopwords
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Stopwords Processor Plugin',
      'description' => 'Unit test of preprocessor for ignoring stopwords.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new Stopwords(array(), 'stopwords', array());;
  }

  /**
   * Get an accessible method of the processor class using reflection.
   */
  public function getAccessibleMethod($methodName) {
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\Stopwords');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Test stopwords process.
   *
   * @dataProvider stopwordsDataProvider
   */
  public function testStopwords($passedString, $expectedString, $stopwordsConfig) {
    $process = $this->getAccessibleMethod('process');

    $configuration = array('file' => '', 'stopwords' => $stopwordsConfig);
    $this->processor->setConfiguration($configuration);
    $process->invokeArgs($this->processor, array(&$passedString));
    $this->assertEquals($passedString, $expectedString);
  }

  /**
   * Data provider for testStopwords().
   *
   * Processor checks for exact case, and tokenized content.
   */
  public function stopwordsDataProvider() {
    return array(
      array(
        "or",
        "",
        array('or'),
      ),
       array(
        "orb",
        "orb",
        array('or'),
      ),
      array(
        "orbital",
        "orbital",
        array('or'),
      ),
      array(
        "ÄÖÜÀÁ<>»«û",
        "ÄÖÜÀÁ<>»«û",
        array('String', 'containing', 'both', 'spaces', 'and', 'Newlines', 'ÄÖÜÀÁ<>»«', )
      ),
      array(
        "ÄÖÜÀÁ",
        "",
        array('String', 'containing', 'both', 'spaces', 'and', 'Newlines', 'ÄÖÜÀÁ', )
      ),
      array(
        " ÄÖÜÀÁ ",
        "",
        array('String', 'containing', 'both', 'spaces', 'and', 'Newlines', 'ÄÖÜÀÁ', )
      ),
    );
  }

  /**
   * Test configuration getStopwords.
   */
  public function testGetStopwords() {
    $getStopwords = $this->getAccessibleMethod('getStopwords');

    $configuration = array('file' => '', 'stopwords' => array("String", "containing", "both", "spaces", "and", "Newlines", "ÄÖÜÀÁ<>»«"));
    $stopwords = array(
      'String',
      'containing',
      'both',
      'spaces',
      'and',
      'Newlines',
      'ÄÖÜÀÁ<>»«',
    );
    $this->processor->setConfiguration($configuration);
    $result = $getStopwords->invoke($this->processor);
    $this->assertTrue(!array_diff($stopwords, $result), 'All stopwords returned');
  }

}
