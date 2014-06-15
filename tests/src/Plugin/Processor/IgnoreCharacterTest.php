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
  public function testCharacterSetsIgnoreCharacter($passedString, $expectedValue, $character_classes) {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCharacter',
      array('processFieldValue'),
      array(array('strip' => array('ignorable' => "['¿¡!?,.:;]", 'character_sets' => $character_classes)), 'string', array()));

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
      array('word_s', 'words', array('Pc' => 'Pc')),
      array('word⁔s', 'words', array('Pc' => 'Pc')),

      array('word〜s', 'words', array('Pd' => 'Pd')),
      array('w–ord⸗s', 'words', array('Pd' => 'Pd')),

      array('word⌉s', 'words', array('Pe' => 'Pe')),
      array('word⦊s〕', 'words', array('Pe' => 'Pe')),

      array('word»s', 'words', array('Pf' => 'Pf')),
      array('word⸍s', 'words', array('Pf' => 'Pf')),

      array('word⸂s', 'words', array('Pi' => 'Pi')),
      array('w«ord⸉s', 'words', array('Pi' => 'Pi')),

      array('words%', 'words', array('Po' => 'Po')),
      array('wo*rd/s', 'words', array('Po' => 'Po')),

      array('word༺s', 'words', array('Ps' => 'Ps')),
      array('w❮ord⌈s', 'words', array('Ps' => 'Ps')),

      array('word៛s', 'words', array('Sc' => 'Sc')),
      array('wo₥rd₦s', 'words', array('Sc' => 'Sc')),

      array('w˓ords', 'words', array('Sk' => 'Sk')),
      array('wo˘rd˳s', 'words', array('Sk' => 'Sk')),

      array('word×s', 'words', array('Sm' => 'Sm')),
      array('wo±rd؈s', 'words', array('Sm' => 'Sm')),

      array('wo᧧rds', 'words', array('So' => 'So')),
      array('w᧶ord᧲s', 'words', array('So' => 'So')),

      //array('worœds', 'words', 'Cc'),
      //array('woƒrds', 'words', 'Cc'),

      array('word۝s', 'words', array('Cf' => 'Cf')),
      array('wo᠎rd؁s', 'words', array('Cf' => 'Cf')),

      array('words', 'words', array('Co' => 'Co')),
      array('wo󿿽rds', 'words', array('Co' => 'Co')),

      array('wordॊs', 'words', array('Mc' => 'Mc')),
      array('worौdংs', 'words', array('Mc' => 'Mc')),

      array('wo⃞rds', 'words', array('Me' => 'Me')),
      array('wor⃤⃟ds', 'words', array('Me' => 'Me')),

      array('woྰrds', 'words', array('Mn' => 'Mn')),
      array('worྵdྶs', 'words', array('Mn' => 'Mn')),

      array('woྰrds', 'words', array('Mn' => 'Mn', 'Pd' => 'Pd', 'Pe' => 'Pe')),
      array('worྵdྶs', 'words', array('Mn' => 'Mn', 'Pd' => 'Pd', 'Pe' => 'Pe')),
    );
  }

}
