<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Component\Utility\String;
use Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter;
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
   * Test processFieldValue method with Punctuation Connector Ignores.
   *
   * @dataProvider textCharacterSetsIgnoreCharacterDataProvider
   */
  public function testCharacterSetsIgnoreCharacter($passedString, $expectedValue, $character_class) {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter',
      array('processFieldValue'),
      array(array('strip' => array('ignorable' => "['¿¡!?,.:;]", 'character_sets' => array($character_class => $character_class))), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    // Decode entities to UTF-8
    $passedString = String::decodeEntities($passedString);
    $processFieldValueMethod->invokeArgs($tokenizerMock, array(&$passedString, 'text'));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Data provider for testValueConfiguration().
   */
  public function textCharacterSetsIgnoreCharacterDataProvider() {
    return array(
      array('word_s', 'words', 'Pc'),
      array('word⁔s', 'words', 'Pc'),

      array('word〜s', 'words', 'Pd'),
      array('w–ord⸗s', 'words', 'Pd'),

      array('word⌉s', 'words', 'Pe'),
      array('word⦊s〕', 'words', 'Pe'),

      array('word»s', 'words', 'Pf'),
      array('word⸍s', 'words', 'Pf'),

      array('word⸂s', 'words', 'Pi'),
      array('w«ord⸉s', 'words', 'Pi'),

      array('words%', 'words', 'Po'),
      array('wo*rd/s', 'words', 'Po'),

      array('word༺s', 'words', 'Ps'),
      array('w❮ord⌈s', 'words', 'Ps'),

      array('word៛s', 'words', 'Sc'),
      array('wo₥rd₦s', 'words', 'Sc'),

      array('w˓ords', 'words', 'Sk'),
      array('wo˘rd˳s', 'words', 'Sk'),

      array('word×s', 'words', 'Sm'),
      array('wo±rd؈s', 'words', 'Sm'),

      array('wo᧧rds', 'words', 'So'),
      array('w᧶ord᧲s', 'words', 'So'),

      //array('worœds', 'words', 'Cc'),
      //array('woƒrds', 'words', 'Cc'),

      array('word۝s', 'words', 'Cf'),
      array('wo᠎rd؁s', 'words', 'Cf'),

      array('words', 'words', 'Co'),
      array('wo󿿽rds', 'words', 'Co'),

      array('wordॊs', 'words', 'Mc'),
      array('worौdংs', 'words', 'Mc'),

      array('wo⃞rds', 'words', 'Me'),
      array('wor⃤⃟ds', 'words', 'Me'),

      array('woྰrds', 'words', 'Mn'),
      array('worྵdྶs', 'words', 'Mn'),
    );
  }

}
