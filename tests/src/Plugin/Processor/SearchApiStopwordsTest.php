<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiStopwordsTest.
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
class SearchApiStopwordsTest extends UnitTestCase {

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
    $this->processor = new Stopwords(array(), 'search_api_stopwords_processor', array());;
  }

  /**
   * Test stopwords process.
   */
  public function testStopwords() {
    $string = 'String containing some stop words and that is to be tested.';
    $configuration = array('file' => '', 'stopwords' => 'some and that');
    $this->processor->setConfiguration($configuration);
    $this->processor->process($string);
    $this->assertEquals($string, 'String containing stop words is to be tested.');
  }

  /**
   * Test configuration getStopwords.
   */
  public function testGetStopwords() {
    $reflectionStopwords = new \ReflectionClass('\Drupal\search_api\Plugin\SearchApi\Processor\Stopwords');
    $getStopwords = $reflectionStopwords->getMethod('getStopwords');
    $getStopwords->setAccessible(TRUE);

    $configuration = array('file' => '', 'stopwords' => "String containing both\nspaces\nand Newlines ÄÖÜÀÁ<>»«");
    $stopwords = array(
      'String' => 0,
      'containing' => 1,
      'both' => 2,
      'spaces' => 3,
      'and' => 4,
      'Newlines' => 5,
      'ÄÖÜÀÁ<>»«' => 6,
    );
    $this->processor->setConfiguration($configuration);
    $result = $getStopwords->invoke($this->processor);
    $this->assertTrue(!array_diff_key($stopwords, $result), 'All stopwords returned');
  }

}
