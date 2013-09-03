<?php

/**
 * @file
 * Contains Drupal\search_api\Tests\UnitTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Class with unit tests testing small fragments of the Search API.
 */
class UnitTest extends DrupalWebTestCase {

  protected $index;

  protected function assertEqual($first, $second, $message = '', $group = 'Other') {
    if (is_array($first) && is_array($second)) {
      return $this->assertTrue($this->deepEquals($first, $second), $message, $group);
    }
    else {
      return parent::assertEqual($first, $second, $message, $group);
    }
  }

  protected function deepEquals($first, $second) {
    if (!is_array($first) || !is_array($second)) {
      return $first == $second;
    }
    $first  = array_merge($first);
    $second = array_merge($second);
    foreach ($first as $key => $value) {
      if (!array_key_exists($key, $second) || !$this->deepEquals($value, $second[$key])) {
        return FALSE;
      }
      unset($second[$key]);
    }
    return empty($second);
  }

  public static function getInfo() {
    return array(
      'name' => 'Test search API components',
      'description' => 'Tests some independent components of the Search API, like the processors.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp('entity', 'search_api');
    $this->index = entity_create('search_api_index', array(
      'id' => 1,
      'name' => 'test',
      'enabled' => 1,
      'item_type' => 'user',
      'options' => array(
        'fields' => array(
          'name' => array(
            'type' => 'text',
          ),
          'mail' => array(
            'type' => 'string',
          ),
          'search_api_language' => array(
            'type' => 'string',
          ),
        ),
      ),
    ));
  }

  public function testUnits() {
    $this->checkQueryParseKeys();
    $this->checkIgnoreCaseProcessor();
    $this->checkTokenizer();
    $this->checkHtmlFilter();
  }

  public function checkQueryParseKeys() {
    $options['parse mode'] = 'direct';
    $mode = &$options['parse mode'];
    $num = 1;
    $query = new DefaultQuery($this->index, $options);
    $modes = $query->parseModes();

    $query->keys('foo');
    $this->assertEqual($query->getKeys(), 'foo', t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('foo bar');
    $this->assertEqual($query->getKeys(), 'foo bar', t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('(foo bar) OR "bar baz"');
    $this->assertEqual($query->getKeys(), '(foo bar) OR "bar baz"', t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));

    $mode = 'single';
    $num = 1;
    $query = new DefaultQuery($this->index, $options);

    $query->keys('foo');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', 'foo'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('foo bar');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', 'foo bar'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('(foo bar) OR "bar baz"');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', '(foo bar) OR "bar baz"'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));

    $mode = 'terms';
    $num = 1;
    $query = new DefaultQuery($this->index, $options);

    $query->keys('foo');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', 'foo'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('foo bar');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', 'foo', 'bar'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    $query->keys('(foo bar) OR "bar baz"');
    $this->assertEqual($query->getKeys(), array('(foo', 'bar)', 'OR', 'bar baz', '#conjunction' => 'AND'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
    // http://drupal.org/node/1468678
    $query->keys('"Münster"');
    $this->assertEqual($query->getKeys(), array('#conjunction' => 'AND', 'Münster'), t('"@mode" parse mode, test !num.', array('@mode' => $modes[$mode]['name'], '!num' => $num++)));
  }

  public function checkIgnoreCaseProcessor() {
    $types = search_api_field_types();
    $orig = 'Foo bar BaZ, ÄÖÜÀÁ<>»«.';
    $processed = drupal_strtolower($orig);
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
          'value' => LANGUAGE_NONE,
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

    $processor = new IgnoreCase($this->index, array('fields' => array('name' => 'name')));
    $tmp = $items;
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed, t('!type field was processed.', array('!type' => 'name')));
    $this->assertEqual($tmp[1]['mail']['value'], $orig, t("!type field wasn't processed.", array('!type' => 'mail')));

    $query = new DefaultQuery($this->index);
    $query->keys('Foo "baR BaZ" fOObAr1');
    $query->condition('name', 'FOO');
    $query->condition('mail', 'BAR');
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), $keys1, t('Search keys were processed correctly.'));
    $this->assertEqual($query->getFilter()->getFilters(), $filters1, t('Filters were processed correctly.'));

    $processor = new IgnoreCase($this->index, array('fields' => array('name' => 'name', 'mail' => 'mail')));
    $tmp = $items;
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed, t('!type field was processed.', array('!type' => 'name')));
    $this->assertEqual($tmp[1]['mail']['value'], $processed, t('!type field was processed.', array('!type' => 'mail')));

    $query = new DefaultQuery($this->index);
    $query->keys('Foo "baR BaZ" fOObAr1');
    $query->condition('name', 'FOO');
    $query->condition('mail', 'BAR');
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), $keys2, t('Search keys were processed correctly.'));
    $this->assertEqual($query->getFilter()->getFilters(), $filters2, t('Filters were processed correctly.'));
  }

  public function checkTokenizer() {
    $orig = 'Foo bar1 BaZ,  La-la-la.';
    $processed1 = array(
      array(
        'value' => 'Foo',
        'score' => 1,
      ),
      array(
        'value' => 'bar1',
        'score' => 1,
      ),
      array(
        'value' => 'BaZ',
        'score' => 1,
      ),
      array(
        'value' => 'Lalala',
        'score' => 1,
      ),
    );
    $processed2 = array(
      array(
        'value' => 'Foob',
        'score' => 1,
      ),
      array(
        'value' => 'r1B',
        'score' => 1,
      ),
      array(
        'value' => 'Z,L',
        'score' => 1,
      ),
      array(
        'value' => 'l',
        'score' => 1,
      ),
      array(
        'value' => 'l',
        'score' => 1,
      ),
      array(
        'value' => '.',
        'score' => 1,
      ),
    );
    $items = array(
      1 => array(
        'name' => array(
          'type' => 'text',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'search_api_language' => array(
          'type' => 'string',
          'original_type' => 'string',
          'value' => LANGUAGE_NONE,
        ),
      ),
    );

    $processor = new Tokenizer($this->index, array('fields' => array('name' => 'name'), 'spaces' => '[^\p{L}\p{N}]', 'ignorable' => '[-]'));
    $tmp = $items;
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed1, t('Value was correctly tokenized with default settings.'));

    $query = new DefaultQuery($this->index, array('parse mode' => 'direct'));
    $query->keys("foo \"bar-baz\" \n\t foobar1");
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), 'foo barbaz foobar1', t('Search keys were processed correctly.'));

    $processor = new Tokenizer($this->index, array('fields' => array('name' => 'name'), 'spaces' => '[-a]', 'ignorable' => '\s'));
    $tmp = $items;
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed2, t('Value was correctly tokenized with custom settings.'));

    $query = new DefaultQuery($this->index, array('parse mode' => 'direct'));
    $query->keys("foo \"bar-baz\" \n\t foobar1");
    $processor->preprocessSearchQuery($query);
    $this->assertEqual($query->getKeys(), 'foo"b r b z"foob r1', t('Search keys were processed correctly.'));
  }

  public function checkHtmlFilter() {
    $orig = <<<END
This is <em lang="en" title =
"something">a test</em>.
How to write <strong>links to <em>other sites</em></strong>: &lt;a href="URL" title="MOUSEOVER TEXT"&gt;TEXT&lt;/a&gt;.
&lt; signs can be <A HREF="http://example.com/topic/html-escapes" TITLE =  'HTML &quot;escapes&quot;'
TARGET = '_blank'>escaped</A> with "&amp;lt;".
<img src = "foo.png" alt = "someone's image" />
END;
    $tags = <<<END
em = 1.5
strong = 2
END;
    $processed1 = array(
      array('value' => 'This', 'score' => 1),
      array('value' => 'is', 'score' => 1),
      array('value' => 'something', 'score' => 1.5),
      array('value' => 'a', 'score' => 1.5),
      array('value' => 'test', 'score' => 1.5),
      array('value' => 'How', 'score' => 1),
      array('value' => 'to', 'score' => 1),
      array('value' => 'write', 'score' => 1),
      array('value' => 'links', 'score' => 2),
      array('value' => 'to', 'score' => 2),
      array('value' => 'other', 'score' => 3),
      array('value' => 'sites', 'score' => 3),
      array('value' => '<a', 'score' => 1),
      array('value' => 'href="URL"', 'score' => 1),
      array('value' => 'title="MOUSEOVER', 'score' => 1),
      array('value' => 'TEXT">TEXT</a>', 'score' => 1),
      array('value' => '<', 'score' => 1),
      array('value' => 'signs', 'score' => 1),
      array('value' => 'can', 'score' => 1),
      array('value' => 'be', 'score' => 1),
      array('value' => 'HTML', 'score' => 1),
      array('value' => '"escapes"', 'score' => 1),
      array('value' => 'escaped', 'score' => 1),
      array('value' => 'with', 'score' => 1),
      array('value' => '"&lt;"', 'score' => 1),
      array('value' => 'someone\'s', 'score' => 1),
      array('value' => 'image', 'score' => 1),
    );
    $items = array(
      1 => array(
        'name' => array(
          'type' => 'text',
          'original_type' => 'text',
          'value' => $orig,
        ),
        'search_api_language' => array(
          'type' => 'string',
          'original_type' => 'string',
          'value' => LANGUAGE_NONE,
        ),
      ),
    );

    $tmp = $items;
    $processor = new HtmlFilter($this->index, array('fields' => array('name' => 'name'), 'title' => TRUE, 'alt' => TRUE, 'tags' => $tags));
    $processor->preprocessIndexItems($tmp);
    $processor = new Tokenizer($this->index, array('fields' => array('name' => 'name'), 'spaces' => '[\s.:]', 'ignorable' => ''));
    $processor->preprocessIndexItems($tmp);
    $this->assertEqual($tmp[1]['name']['value'], $processed1, t('Text was correctly processed.'));
  }

}
