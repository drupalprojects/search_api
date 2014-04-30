<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\SearchApiHighlightTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Core\Language\Language as CoreLanguage;
use Drupal\search_api\Plugin\SearchApi\Processor\Language;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Language processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class SearchApiLanguageTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\Language
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

    $this->processor = new Language(array(), 'search_api_language_processor', array());
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation_manager */
    $translation_manager = $this->getStringTranslationStub();
    $this->processor->setTranslationManager($translation_manager);
  }

  /**
   * Tests whether the "Item language" field is properly added to the index.
   *
   * @see \Drupal\search_api\Plugin\SearchApi\Processor\Language::alterPropertyDefinitions()
   */
  public function testAlterProperties() {
    // Tests whether the property gets properly added to the
    // datasource-independent properties.
    /** @var \Drupal\Core\TypedData\DataDefinitionInterface[] $properties */
    $properties = array();
    $this->processor->alterPropertyDefinitions($properties);
    $this->assertTrue(!empty($properties['search_api_language']), '"search_api_language" property got added.');
    if (!empty($properties['search_api_language'])) {
      $this->assertInstanceOf('Drupal\Core\TypedData\DataDefinitionInterface', $properties['search_api_language'], 'Added "search_api_language" property implements the necessary interface.');
      $this->assertEquals($properties['search_api_language']->getLabel(), 'Item language', 'Correct label for "search_api_language" property.');
      $this->assertEquals($properties['search_api_language']->getDescription(), 'The language code of the item.', 'Correct description for "search_api_language" property.');
      $this->assertEquals($properties['search_api_language']->getDataType(), 'string', 'Correct type for "search_api_language" property.');
    }

    // Tests whether the properties of specific datasources stay untouched.
    $properties = array();
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    $datasource = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $this->processor->alterPropertyDefinitions($properties, $datasource);
    $this->assertEmpty($properties, 'Datasource-specific properties did not get changed.');
  }

  /**
   * Tests whether the "Item language" field is properly added to indexed items.
   *
   * @see \Drupal\search_api\Plugin\SearchApi\Processor\Language::preprocessIndexItems()
   */
  public function testPreprocessIndexItems() {
    $item = array(
      'search_api_language' => array(
        'type' => 'string',
        'value' => array(),
        'original_type' => NULL,
      ),
    );
    $item['#item'] = $this->getMock('Drupal\Core\TypedData\TranslatableInterface');
    $item['#item']->expects($this->any())
      ->method('language')
      ->will($this->returnValue(new CoreLanguage(array('id' => 'en'))));
    $items = array($item);
    $item['#item'] = $this->getMock('Drupal\Core\TypedData\TranslatableInterface');
    $item['#item']->expects($this->any())
      ->method('language')
      ->will($this->returnValue(new CoreLanguage(array('id' => 'es'))));
    $items[] = $item;
    $item['#item'] = $this->getMock('Drupal\search_api\Tests\TestComplexDataInterface');
    $items[] = $item;
    $this->processor->preprocessIndexItems($items);
    $this->assertEquals('string', $items[0]['search_api_language']['original_type'], 'The "Item language" original type was correctly set.');
    $this->assertEquals(array('en'), $items[0]['search_api_language']['value'], 'The "Item language" value was correctly set for an English item.');
    $this->assertEquals(array('es'), $items[1]['search_api_language']['value'], 'The "Item language" value was correctly set for a Spanish item.');
    $this->assertEquals(array(CoreLanguage::LANGCODE_NOT_SPECIFIED), $items[2]['search_api_language']['value'], 'The "Item language" value was correctly set for a non-translatable item.');
  }

}
