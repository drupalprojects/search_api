<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiHighlightTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Highlight;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Highlight processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
// @todo Rewrite this whole class:
//   - Each method should only test a single thing.
//   - The tests should use the processor itself instead of a mock. Use mocks
//     for index, datasource, etc., if necessary.
//   - The tests should only test the interface methods (in this case, probably
//     only postprocessSearchResults()) instead of implementation details.
/*
How to create search results:
$results = Utility::createSearchResultSet($query);
$results->setResultCount(2);
$result_items = array(
  'test:1' => Utility::createItem($this->index, 'test:1'),
  'test:2' => Utility::createItem($this->index, 'test:2'),
);
$results->setResultItems($result_items);
$field = Utility::createField($this->index, 'field1');
…
$result_items['test:1']->setField('field1', $field);
…
*/
class SearchApiHighlightTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Highlight
   */
  protected $processor;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Highlight Processor Plugin',
      'description' => 'Unit tests of postprocessor excerpt highlighting.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    // @todo Also set up an index here.
    $this->processor = new Highlight(array(), 'search_api_highlight_processor', array());
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->processor->setStringTranslation($translation);
  }

  /**
   * Test postprocessSearchResults.
   *
   * Checks configuration changes to what is sent to be highlighted.
   */
  public function testPostProcessSearchResults() {
    // Old code:
    /*
    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');

    $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
      ->setMethods(array('getKeywords'))
      ->setConstructorArgs(array(array(), 'search_api_highlight_processor', array()))
      ->getMock();
    $response = array(
      'result count' => 2,
      'results' => array(
        1 => array(),
        array(
          2 => array(),
        ),
      ),
    );

    // Trivial case. No keywords.
    $processor->expects($this->once())
      ->method('getKeywords');
    $result = $response;
    $processor->postprocessSearchResults($response, $query);
    $this->assertEquals($response, $result, 'Nothing done if no keys selected.');

    // Keys are set; and highlight is enabled ('always').
    $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
      ->setMethods(array('postprocessExcerptResults', 'postprocessFieldResults', 'getKeywords'))
      ->setConstructorArgs(array(array(), 'search_api_highlight_processor', array()))
      ->getMock();
    $processor->expects($this->once())
      ->method('getKeywords')
      ->will($this->returnValue(array('mail', 'text')));
    $processor->expects($this->exactly(2))
      ->method('postprocessFieldResults');
    $processor->expects($this->never())
      ->method('postprocessExcerptResults');
    $configuration = array(
      'highlight' => 'always',
      'excerpt' => 0,
    );
    $processor->setConfiguration($configuration);
    $processor->postprocessSearchResults($response, $query);

    // Keys are set; and highlight is disabled. Excerpt is enabled.
    $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
      ->setMethods(array('postprocessExcerptResults', 'postprocessFieldResults', 'getKeywords'))
      ->setConstructorArgs(array(array(), 'search_api_highlight_processor', array()))
      ->getMock();
    $configuration = array(
      'highlight' => 'never',
      'excerpt' => 1,
    );
    $processor->expects($this->once())
      ->method('getKeywords')
      ->will($this->returnValue(array('mail', 'text')));
    $processor->expects($this->exactly(2))
      ->method('postprocessExcerptResults');
    $processor->expects($this->never())
      ->method('postprocessFieldResults');
    $processor->setConfiguration($configuration);
    $processor->postprocessSearchResults($response, $query);
    */
  }

  /**
   * Tests createExcerpt, and highlighField with simulated search keywords.
   *
   * Checks highlighting, excerpting, seperators.
   */
  public function testSearchExcerpt() {
    // Old code:
    /*
    $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
      ->setConstructorArgs(array(array(), 'search_api_highlight_processor', array()))
      ->getMock();
    // Access protected createExcerpt method.
    $reflectionProcessor = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\Highlight');
    $createExcerpt = $reflectionProcessor->getMethod('createExcerpt');
    $createExcerpt->setAccessible(TRUE);


    // Make some text with entities and tags.
    $text = 'The <strong>quick</strong> <a href="#">brown</a> fox &amp; jumps <h2>over</h2> the lazy dog';
    // Note: The createExcrept method adds some extra spaces -- not
    // important for HTML formatting. Remove these for comparison.
    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $text, array('nothing')));
    $this->assertEmpty($result, 'Nothing is returned when keyword is not found in short string');

    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $text, array('fox')));
    $this->assertEquals($result, 'The quick brown <strong>fox</strong> &amp; jumps over the lazy dog', 'Found keyword is highlighted');

    $expected = '<strong>The</strong> quick brown fox &amp; jumps over <strong>the</strong> lazy dog';
    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $text, array('The')));
    $this->assertEquals(preg_replace('| +|', ' ', $result), $expected, 'Keyword is highlighted at beginning of short string');

    $expected = 'The quick brown fox &amp; jumps over the lazy <strong>dog</strong>';
    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $text, array('dog')));
    $this->assertEquals(preg_replace('| +|', ' ', $result), $expected, 'Keyword is highlighted at end of short string');

    $longtext = str_repeat($text . ' ', 10);
    $longtext .= ' cat ' . $longtext . ' rabbit ' . $longtext . ' mouse ';
    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $longtext, array('nothing')));
    $this->assertEmpty($result, 'When keyword is not found in long string, nothing returned.');

    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $longtext, array('fox')));
    $expected = 'The quick brown <strong>fox</strong> &amp; jumps over the lazy dog The quick brown <strong>fox</strong> &amp; jumps over the lazy dog The quick brown <strong>fox</strong> &amp; jumps over the lazy dog The quick brown <strong>fox</strong> &amp; ...';
    $this->assertEquals($result, $expected, 'Snipets filled with multiple instances of a string.');


    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $longtext, array('cat', 'rabbit')));
    $expected = '... The quick brown fox &amp; jumps over the lazy dog <strong>cat</strong> The quick brown fox &amp; jumps over the lazy dog The ... The quick brown fox &amp; jumps over the lazy dog <strong>rabbit</strong> The quick brown fox &amp; jumps over the lazy dog ...';
    $this->assertEquals($result, $expected, 'Mulitple snippets are included when keywords are spread through string');

    $configuration = $processor->getConfiguration();
    $configuration['excerpt_length'] = 70;
    $processor->setConfiguration($configuration);

    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $longtext, array('cat', 'mouse')));
    $expected = '... The quick brown fox &amp; jumps over the lazy dog <strong>cat</strong> The quick brown fox &amp; jumps over the lazy dog The ... The quick brown fox &amp; jumps over the lazy dog <strong>mouse</strong>';
    $this->assertEquals($result, $expected, 'Search snippets returned in shortened excerpt length.');

    $entities = str_repeat('k&eacute;sz &iacute;t&eacute;se ', 20);
    $result = preg_replace('| +|', ' ', $createExcerpt->invoke($processor, $entities, array('kész')));
    $this->assertFalse(strpos($result, '&'), 'Entities are not present in excerpt');
    $this->assertTrue(strpos($result, 'í') > 0, 'Entities are converted in excerpt');

    // The node body that will produce this rendered $text is:
    // 123456789 HTMLTest +123456789+&lsquo;  +&lsquo;  +&lsquo;  +&lsquo;  +12345678  &nbsp;&nbsp;  +&lsquo;  +&lsquo;  +&lsquo;   &lsquo;
    $text = "<div class=\"field field-name-body field-type-text-with-summary field-label-hidden\"><div class=\"field-items\"><div class=\"field-item even\" property=\"content:encoded\"><p>123456789 HTMLTest +123456789+‘  +‘  +‘  +‘  +12345678      +‘  +‘  +‘   ‘</p>\n</div></div></div> ";
    $result = $createExcerpt->invoke($processor, $text, array('HTMLTest'));
    $this->assertFalse(empty($result),  'Rendered Multi-byte HTML encodings are not corrupted in search excerpts');
    */
  }

}
