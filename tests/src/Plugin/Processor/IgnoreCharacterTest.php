<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

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

  use ProcessorTestTrait;

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

    $this->processor = new IgnoreCharacter(array('strip' => array('ignorable' => "['¿¡!?,.:;]")), 'string', array());
  }

  /**
   * Test processFieldValue method with Punctuation Connector Ignores.
   *
   * @dataProvider textCharacterSetsIgnoreCharacterDataProvider
   */
  public function testCharacterSetsIgnoreCharacter($passedString, $expectedValue, $character_classes) {
    $this->processor->setConfiguration(array('strip' => array('character_sets' => $character_classes)));
    $this->invokeMethod('processFieldValue', array(&$passedString, 'text'));
    $this->assertEquals($expectedValue, $passedString);
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

      array("wor\x0Ads", 'words', array('Cc' => 'Cc')),
      array("wo\x0Crds", 'words', array('Cc' => 'Cc')),

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
