<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\HighlightTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\Highlight;
use Drupal\search_api\Tests\Processor\TestItemsTrait;
use Drupal\search_api\Utility\Utility;
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

class HighlightTest extends UnitTestCase {

  use TestItemsTrait;

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
  }


  /**
   * Test postprocessSearchResults.
   */
  public function testpostprocessSearchResultsWithoutQuerykeys() {
    $this->createHighlightProcessor();

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');

    $results = $this->getMockBuilder('\Drupal\search_api\Query\ResultSet')
      ->setMethods(array('getResultCount'))
      ->setConstructorArgs(array($query))
      ->getMock();

    $results->expects($this->once())
      ->method('getResultCount')
      ->will($this->returnValue(0));

    $output = $this->processor->postprocessSearchResults($results);
    $this->assertEmpty($output, "No results found");
  }


  /**
   * Check to see what happens when there are no keywords set, should just return
   */
  public function testpostprocessSearchResultsWithoutKeywords() {
    $this->createHighlightProcessor();

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');

    $results = $this->getMockBuilder('\Drupal\search_api\Query\ResultSet')
      ->setMethods(array('getResultCount', 'getQuery'))
      ->setConstructorArgs(array($query))
      ->getMock();

    $query->expects($this->once())
      ->method('getKeys')
      ->will($this->returnValue(array()));

    $results->expects($this->once())
      ->method('getResultCount')
      ->will($this->returnValue(1));
    $results->expects($this->once())
      ->method('getQuery')
      ->will($this->returnValue($query));

    $output = $this->processor->postprocessSearchResults($results);
    $this->assertEmpty($output, "No results found");
  }

  /**
   * Test to see if we have the correct result when searching for "foo" on "Some foo text"
   */
  public function testpostprocessSearchResultsWithCorrectResult() {
    $this->createHighlightProcessor();

    $body_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'body';

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');
    $query->expects($this->atLeastOnce())
      ->method('getKeys')
      ->will($this->returnValue(array('foo' => 'foo')));

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $field = Utility::createField($index, $body_field_id);
    $field->setType('text');

    $index->expects($this->atLeastOnce())
      ->method('getFields')
      ->will($this->returnValue(array($body_field_id => $field)));

    $this->processor->setIndex($index);

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array('Some foo value');
    $fields = array(
      $body_field_id => array(
        'type' => 'text',
        'values' => $body_value,
      ),
    );

    $items = $this->createItems($index, 1, $fields, $node);
    $items_keys = array_keys($items);
    $first_entity_key = $items_keys[0];

    $results = Utility::createSearchResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getExtraData('highlighted_fields');
    $this->assertEquals('Some <strong>foo</strong> value', $output[$first_entity_key][$body_field_id][0], 'Highlight is not correctly applied');
  }

  /**
   * Test to see if we can change the prefix
   */
  public function testpostprocessSearchResultsWithChangedPrefixSuffix() {
    $this->createHighlightProcessor(array('prefix' => '<em>', 'suffix' => '</em>'));

    $body_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'body';

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');
    $query->expects($this->atLeastOnce())
      ->method('getKeys')
      ->will($this->returnValue(array('foo' => 'foo')));

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $field = Utility::createField($index, $body_field_id);
    $field->setType('text');

    $index->expects($this->atLeastOnce())
      ->method('getFields')
      ->will($this->returnValue(array($body_field_id => $field)));

    $this->processor->setIndex($index);

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array('Some foo value');
    $fields = array(
      $body_field_id => array(
        'type' => 'text',
        'values' => $body_value,
      ),
    );

    $items = $this->createItems($index, 1, $fields, $node);
    $items_keys = array_keys($items);
    $first_entity_key = $items_keys[0];

    $results = Utility::createSearchResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getExtraData('highlighted_fields');
    $this->assertEquals('Some <em>foo</em> value', $output[$first_entity_key][$body_field_id][0], 'Highlight is not correctly applied');
  }

  /**
   * Test to see if we can change the prefix
   */
  public function testpostprocessSearchResultsWithChangedHighlight() {
    $this->createHighlightProcessor(array('highlight' => 'never'));

    $body_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'body';

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');
    $query->expects($this->atLeastOnce())
      ->method('getKeys')
      ->will($this->returnValue(array('foo' => 'foo')));

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $field = Utility::createField($index, $body_field_id);
    $field->setType('text');

    $index->expects($this->atLeastOnce())
      ->method('getFields')
      ->will($this->returnValue(array($body_field_id => $field)));

    $this->processor->setIndex($index);

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array('Some foo value');
    $fields = array(
      $body_field_id => array(
        'type' => 'text',
        'values' => $body_value,
      ),
    );

    $items = $this->createItems($index, 1, $fields, $node);

    $results = Utility::createSearchResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getExtraData('highlighted_fields');
    $this->assertEmpty($output, 'Highlight is\'t applied');
  }

  /**
   * Test to see if highlight works on a longer text
   */
  public function testpostprocessSearchResultsExerpt() {
    $this->createHighlightProcessor();

    $body_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'body';

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');
    $query->expects($this->atLeastOnce())
      ->method('getKeys')
      ->will($this->returnValue(array('congue' => 'congue')));

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $field = Utility::createField($index, $body_field_id);
    $field->setType('text');

    $index->expects($this->atLeastOnce())
      ->method('getFields')
      ->will($this->returnValue(array($body_field_id => $field)));

    $this->processor->setIndex($index);

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array($this->getFieldBody());
    $fields = array(
      $body_field_id => array(
        'type' => 'text',
        'values' => $body_value,
      ),
    );

    $items = $this->createItems($index, 1, $fields, $node);
    $items_keys = array_keys($items);
    $first_entity_key = $items_keys[0];

    $results = Utility::createSearchResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$first_entity_key]->getExcerpt();;
    $correct_output = '...  ligula sit amet condimentum dapibus, lorem nunc <strong>congue</strong> velit, et dictum augue leo sodales augue.
Maecenas eget ...';
    $this->assertEquals($correct_output, $excerpt, 'Highlight is not correctly applied');
  }

  /**
   * Test to see if highlight works with a changed excerpt length
   */
  public function testpostprocessSearchResultsWithChangedExerptLength() {
    $this->createHighlightProcessor(array('excerpt_length' => 64));

    $body_field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'body';

    $query = $this->getMock('Drupal\search_api\Query\QueryInterface');
    $query->expects($this->atLeastOnce())
      ->method('getKeys')
      ->will($this->returnValue(array('congue' => 'congue')));

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');

    $field = Utility::createField($index, $body_field_id);
    $field->setType('text');

    $index->expects($this->atLeastOnce())
      ->method('getFields')
      ->will($this->returnValue(array($body_field_id => $field)));

    $this->processor->setIndex($index);

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $body_value = array($this->getFieldBody());
    $fields = array(
      $body_field_id => array(
        'type' => 'text',
        'values' => $body_value,
      ),
    );

    $items = $this->createItems($index, 1, $fields, $node);
    $items_keys = array_keys($items);
    $first_entity_key = $items_keys[0];

    $results = Utility::createSearchResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(1);

    $this->processor->postprocessSearchResults($results);

    $output = $results->getResultItems();
    $excerpt = $output[$first_entity_key]->getExcerpt();;
    $correct_output = '...  nunc <strong>congue</strong> velit, ...';
    $this->assertEquals($correct_output, $excerpt, 'Highlight is not correctly applied');
  }



  /**
   * Create Highlight processor
   * @param array $configuration
   */
  private function createHighlightProcessor($configuration = array()) {
    $default_configuration = array(
      'prefix' => '<strong>',
      'suffix' => '</strong>',
      'excerpt' => TRUE,
      'excerpt_length' => 256,
      'highlight' => 'always',
    );
    $configuration += $default_configuration;

    $this->processor = new Highlight($configuration, 'highlight', array());

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $this->getStringTranslationStub();
    $this->processor->setStringTranslation($translation);
  }


  /**
   * Returns a longer string to work with
   * @return string
   */
  private function getFieldBody() {
    return "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris dictum ultricies sapien id consequat.
Fusce tristique erat at dui ultricies, eu rhoncus odio rutrum. Praesent viverra mollis mauris a cursus.
Curabitur at condimentum orci. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.
Praesent suscipit massa non pretium volutpat. Suspendisse id lacus facilisis, fringilla mauris vitae, tincidunt turpis.
Proin a euismod libero. Nam aliquet neque nulla, nec placerat libero accumsan id. Quisque sit amet consequat lacus.
Donec mauris erat, iaculis id nisl nec, dapibus posuere lectus. Sed ultrices libero id elit volutpat sagittis.
Donec a tortor ullamcorper, tempus lectus at, ultrices felis. Nam nibh magna, dictum in massa ut, ornare venenatis enim.
Phasellus enim massa, condimentum eu sem vel, consectetur fermentum erat. Cras porttitor ut dolor interdum vehicula.
Vestibulum erat arcu, placerat quis gravida quis, venenatis vel magna. Pellentesque pellentesque lacus ut feugiat auctor.
Mauris libero magna, dictum in fermentum nec, blandit non augue.
Morbi sed viverra libero.Phasellus sem velit, sollicitudin in felis lacinia, suscipit auctor dolor.
Praesent dignissim dolor sed lobortis mattis.
Ut tristique, ligula sit amet condimentum dapibus, lorem nunc congue velit, et dictum augue leo sodales augue.
Maecenas eget mi ac massa sagittis malesuada. Fusce ac purus vel ipsum imperdiet vulputate.
Mauris vestibulum sapien sit amet elementum tincidunt. Aenean sollicitudin tortor pulvinar ante commodo sagittis.
Integer in nisi consequat, elementum felis in, consequat purus. Maecenas blandit ipsum id tellus accumsan, sit amet venenatis orci vestibulum.
Ut id erat venenatis, vehicula mi eget, gravida odio. Etiam dapibus purus in massa condimentum, vitae lobortis est aliquam.
Morbi tristique velit et sem varius rhoncus. In tincidunt sagittis libero. Integer interdum sit amet sem sit amet sodales.
Donec sit amet arcu sit amet leo tristique dignissim vel ut enim. Nulla faucibus lacus eu adipiscing semper. Sed ut sodales erat.
Sed mauris purus, tempor non eleifend et, mollis ut lacus. Etiam interdum velit justo, nec imperdiet nunc pulvinar sit amet.
Sed eu lacus eget augue laoreet vehicula id sed sem. Maecenas at condimentum massa, et pretium nulla. Aliquam sed nibh velit.
Quisque turpis lacus, sodales nec malesuada nec, commodo non purus.
Cras pellentesque, lectus ut imperdiet euismod, purus sem convallis tortor, ut fermentum elit nulla et quam.
Mauris luctus mattis enim non accumsan. Sed consequat sapien lorem, in ultricies orci posuere nec.
Fusce in mauris eu leo fermentum feugiat. Proin varius diam ante, non eleifend ipsum luctus sed.";
  }




  /**
   * Test postprocessSearchResults.
   *
   * Checks configuration changes to what is sent to be highlighted.
   */
//  public function testPostProcessSearchResults() {
  // Old code:

  /*
  $query = $this->getMock('Drupal\search_api\Query\QueryInterface');

  $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
    ->setMethods(array('getKeywords'))
    ->setConstructorArgs(array(array(), 'highlight', array()))
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
    ->setConstructorArgs(array(array(), 'highlight', array()))
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
    ->setConstructorArgs(array(array(), 'highlight', array()))
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
//  }

  /**
   * Tests createExcerpt, and highlightField with simulated search keywords.
   *
   * Checks highlighting, excerpting, seperators.
   */
//  public function testSearchExcerpt() {
  // Old code:
  /*
  $processor = $this->getMockBuilder('\Drupal\search_api\Plugin\SearchApi\Processor\Highlight')
    ->setConstructorArgs(array(array(), 'highlight', array()))
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
//  }

}
