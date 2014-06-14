<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TokenizerTest.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Tokenizer processor plugin.
 *
 * @group Drupal
 * @group search_api
 */
class TokenizerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tokenizer processor test',
      'description' => 'Test if Tokenizer processor works.',
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
    $class = new \ReflectionClass('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer');
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Test processFieldValue method with title fetching enabled.
   *
   * @dataProvider textDataProvider
   */
  public function testStringTokenizing($passedString, $expectedValue) {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array(), 'string', array()));

    $processFieldValueMethod = $this->getAccessibleMethod('processFieldValue');
    $processFieldValueMethod->invokeArgs($tokenizerMock, array(&$passedString, 'text'));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Data provider for testValueConfiguration().
   */
  public function textDataProvider() {
    return array(
      array('word', array(0 => array('value' => 'word'))),
      array('word word', array(0 => array('value' => 'word'), 1 => array('value' => 'word'))),
      array('words word', array(0 => array('value' => 'words'), 1 => array('value' => 'word'))),
    );
  }


  /**
   * Verifies that strings of CJK characters are tokenized.
   *
   * The search_simplify() function does special things with numbers, symbols,
   * and punctuation. So we only test that CJK characters that are not in these
   * character classes are tokenized properly. See PREG_CLASS_CKJ for more
   * information.
   */
  public function testTokenizer() {
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array('minimum_word_size' => 1, 'overlap_cjk' => true), 'string', array()));

    $simplifyTextMethod = $this->getAccessibleMethod('simplifyText');

    // Create a string of CJK characters from various character ranges in
    // the Unicode tables.

    // Beginnings of the character ranges.
    $starts = array(
      'CJK unified' => 0x4e00,
      'CJK Ext A' => 0x3400,
      'CJK Compat' => 0xf900,
      'Hangul Jamo' => 0x1100,
      'Hangul Ext A' => 0xa960,
      'Hangul Ext B' => 0xd7b0,
      'Hangul Compat' => 0x3131,
      'Half non-punct 1' => 0xff21,
      'Half non-punct 2' => 0xff41,
      'Half non-punct 3' => 0xff66,
      'Hangul Syllables' => 0xac00,
      'Hiragana' => 0x3040,
      'Katakana' => 0x30a1,
      'Katakana Ext' => 0x31f0,
      'CJK Reserve 1' => 0x20000,
      'CJK Reserve 2' => 0x30000,
      'Bomofo' => 0x3100,
      'Bomofo Ext' => 0x31a0,
      'Lisu' => 0xa4d0,
      'Yi' => 0xa000,
    );

    // Ends of the character ranges.
    $ends = array(
      'CJK unified' => 0x9fcf,
      'CJK Ext A' => 0x4dbf,
      'CJK Compat' => 0xfaff,
      'Hangul Jamo' => 0x11ff,
      'Hangul Ext A' => 0xa97f,
      'Hangul Ext B' => 0xd7ff,
      'Hangul Compat' => 0x318e,
      'Half non-punct 1' => 0xff3a,
      'Half non-punct 2' => 0xff5a,
      'Half non-punct 3' => 0xffdc,
      'Hangul Syllables' => 0xd7af,
      'Hiragana' => 0x309f,
      'Katakana' => 0x30ff,
      'Katakana Ext' => 0x31ff,
      'CJK Reserve 1' => 0x2fffd,
      'CJK Reserve 2' => 0x3fffd,
      'Bomofo' => 0x312f,
      'Bomofo Ext' => 0x31b7,
      'Lisu' => 0xa4fd,
      'Yi' => 0xa48f,
    );

    // Generate characters consisting of starts, midpoints, and ends.
    $chars = array();
    $char_codes = array();
    foreach ($starts as $key => $value) {
      $char_codes[] = $starts[$key];
      $chars[] = $this->code2utf($starts[$key]);
      $mid = round(0.5 * ($starts[$key] + $ends[$key]));
      $char_codes[] = $mid;
      $chars[] = $this->code2utf($mid);
      $char_codes[] = $ends[$key];
      $chars[] = $this->code2utf($ends[$key]);
    }

    // Merge into a string and tokenize.
    $text = implode('', $chars);

    $simplified_text = trim($simplifyTextMethod->invokeArgs($tokenizerMock, array($text)));
    $expected = Unicode::strtolower(implode(' ', $chars));

    // Verify that the output matches what we expect.
    $this->assertEquals($simplified_text, $expected, 'CJK tokenizer worked on all supplied CJK characters');
  }

  /**
   * Verifies that strings of non-CJK characters are not tokenized.
   *
   * This is just a sanity check - it verifies that strings of letters are
   * not tokenized.
   */
  public function testNoTokenizer() {
    // Set the minimum word size to 1 (to split all CJK characters) and make
    // sure CJK tokenizing is turned on.
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array('minimum_word_size' => 1, 'overlap_cjk' => true), 'string', array()));

    $simplifyTextMethod = $this->getAccessibleMethod('simplifyText');

    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $out = trim($simplifyTextMethod->invokeArgs($tokenizerMock, array($letters)));

    $this->assertEquals($letters, $out, 'Letters are not CJK tokenized');
  }

  /**
   * Like PHP chr() function, but for unicode characters.
   *
   * chr() only works for ASCII characters up to character 255. This function
   * converts a number to the corresponding unicode character. Adapted from
   * functions supplied in comments on several functions on php.net.
   */
  protected function code2utf($num) {
    if ($num < 128) {
      return chr($num);
    }

    if ($num < 2048) {
      return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
    }

    if ($num < 65536) {
      return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    }

    if ($num < 2097152) {
      return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    }

    return '';
  }

  /**
   * Tests that all Unicode characters simplify correctly.
   *
   * This test uses a Drupal core search file that was constructed so that the even lines are
   * boundary characters, and the odd lines are valid word characters. (It
   * was generated as a sequence of all the Unicode characters, and then the
   * boundary chararacters (punctuation, spaces, etc.) were split off into
   * their own lines).  So the even-numbered lines should simplify to nothing,
   * and the odd-numbered lines we need to split into shorter chunks and
   * verify that simplification doesn't lose any characters.
   *
   */
  public function testSearchSimplifyUnicode() {
    // Set the minimum word size to 1 (to split all CJK characters) and make
    // sure CJK tokenizing is turned on.
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array('minimum_word_size' => 1, 'overlap_cjk' => true), 'string', array()));

    $simplifyTextMethod = $this->getAccessibleMethod('simplifyText');

    $input = file_get_contents(DRUPAL_ROOT . '/core/modules/search/tests/UnicodeTest.txt');
    $basestrings = explode(chr(10), $input);
    $strings = array();
    foreach ($basestrings as $key => $string) {
      if ($key %2) {
        // Even line - should simplify down to a space.
        $simplified = $simplifyTextMethod->invokeArgs($tokenizerMock, array($string));
        $this->assertEquals($simplified, ' ', "Line $key is excluded from the index");
      }
      else {
        // Odd line, should be word characters.
        // Split this into 30-character chunks, so we don't run into limits
        // of truncation in search_simplify().
        $start = 0;
        while ($start < Unicode::strlen($string)) {
          $newstr = Unicode::substr($string, $start, 30);
          // Special case: leading zeros are removed from numeric strings,
          // and there's one string in this file that is numbers starting with
          // zero, so prepend a 1 on that string.
          if (preg_match('/^[0-9]+$/', $newstr)) {
            $newstr = '1' . $newstr;
          }
          $strings[] = $newstr;
          $start += 30;
        }
      }
    }
    foreach ($strings as $key => $string) {
      $simplified = $simplifyTextMethod->invokeArgs($tokenizerMock, array($string));
      $this->assertTrue(Unicode::strlen($simplified) >= Unicode::strlen($string), "Nothing is removed from string $key.");
    }

    // Test the low-numbered ASCII control characters separately. They are not
    // in the text file because they are problematic for diff, especially \0.
    $string = '';
    for ($i = 0; $i < 32; $i++) {
      $string .= chr($i);
    }
    $this->assertEquals(' ', $simplifyTextMethod->invokeArgs($tokenizerMock, array($string)), 'Search simplify works for ASCII control characters.');
  }

  /**
   * Tests that search_simplify() does the right thing with punctuation.
   */
  /*public function testSearchSimplifyPunctuation() {



  }*/

  /**
   * Test processFieldValue method with title fetching enabled.
   *
   * @dataProvider searchSimplifyPunctuationProvider
   */
  public function testSearchSimplifyPunctuation($passedString, $expectedValue, $message) {
    // Set the minimum word size to 1 (to split all CJK characters) and make
    // sure CJK tokenizing is turned on.
    $tokenizerMock = $this->getMock('Drupal\search_api\Plugin\SearchApi\Processor\Tokenizer',
      array('processFieldValue'),
      array(array('minimum_word_size' => 1, 'overlap_cjk' => true), 'string', array()));

    $simplifyTextMethod = $this->getAccessibleMethod('simplifyText');
    $out = trim($simplifyTextMethod->invokeArgs($tokenizerMock, array($passedString)));
    $this->assertEquals($out, $expectedValue, $message);
  }

  /**
   * Data provider for testSearchSimplifyPunctuation().
   */
  public function searchSimplifyPunctuationProvider() {
    $cases = array(
      array('20.03/94-28,876', '20039428876', 'Punctuation removed from numbers'),
      array('great...drupal--module', 'great drupal module', 'Multiple dot and dashes are word boundaries'),
      array('very_great-drupal.module', 'verygreatdrupalmodule', 'Single dot, dash, underscore are removed'),
      array('regular,punctuation;word', 'regular punctuation word', 'Punctuation is a word boundary'),
    );
    return $cases;
  }

}
