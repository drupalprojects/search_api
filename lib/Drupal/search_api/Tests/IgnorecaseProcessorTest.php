<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\IgnorecaseProcessorTestCase
 * @todo woek out why we need to use absolute namespaces when
 * referncing classes, eg \stdClass
 */
namespace Drupal\search_api\Tests;

use Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Language\Language;
use Drupal\Component\Utility\Unicode;

class IgnorecaseProcessorTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\ProcessorPluginBase
   */
  protected $processor;

  /**
   * Modules to enabled.
   *
   * @var array
   */
  public static $modules = array('search_api');

  public static function getInfo() {
    return array(
      'name' => 'Ignore case Processor Plugin',
      'description' => 'Unit test of processor ignores case of strings.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new Ignorecase(array(), 'search_api_ignorecase_processor', array());
  }

  /**
   * Commented out until the SearchApiAbstractProcessor can call service rather
   * than external dependency for search_api_is_text_type
   */
  public function __testIgnorecase() {
    $orig = 'Foo bar BaZ, ÄÖÜÀÁ<>»«.';
    $processed = Unicode::strtolower($orig);
    $items = array(
      1 => array(
        'name' => array(
          'type' => 'text',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'mail' => array(
          'type' => 'string',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'search_api_language' => array(
          'type' => 'string',
          'original_type' => 'string',
          'value' => Language::LANGCODE_NOT_SPECIFIED,
        ),
      ),
    );
    $keys1 = $keys2 = array(
      'foo',
      'bar baz',
      'foobar1',
      '#conjunction' => 'AND',
    );
    $filters1 = array(
      array('name', 'foo', '='),
      array('mail', 'BAR', '='),
    );
    $filters2 = array(
      array('name', 'foo', '='),
      array('mail', 'bar', '='),
    );

    $tmp = $items;

    $this->processor->setConfiguration(array('fields' => array('name' => 'name')));
    $this->processor->preprocessIndexItems($tmp);
    $this->assertEquals($tmp[1]['name']['value'], $processed, 'Name field was processed.');
    $this->assertEquals($tmp[1]['mail']['value'], $orig, "Mail field wasn't procesed.");
/**
 * @todo requries an index. Mocked?
 *
    $query = new SearchApiQuery($this->index);
    $query->keys('Foo "baR BaZ" fOObAr1');
    $query->condition('name', 'FOO');
    $query->condition('mail', 'BAR');
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), $keys1, 'Search keys were processed correctly.');
    $this->assertEqual($query->getFilter()->getFilters(), $filters1, 'Filters were processed correctly.');

    $processor = new SearchApiIgnoreCase($this->index, array('fields' => array('name' => 'name', 'mail' => 'mail')));
    $tmp = $items;
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed, 'Name field was processed.');
    $this->assertEqual($tmp[1]['mail']['value'], $processed, 'Mail field was processed.');

    $query = new SearchApiQuery($this->index);
    $query->keys('Foo "baR BaZ" fOObAr1');
    $query->condition('name', 'FOO');
    $query->condition('mail', 'BAR');
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), $keys2, 'Search keys were processed correctly.');
    $this->assertEqual($query->getFilter()->getFilters(), $filters2, 'Filters were processed correctly.'); 
*/
  }
}
