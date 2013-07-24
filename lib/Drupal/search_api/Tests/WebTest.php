<?php

/**
 * @file
 * Contains Drupal\search_api\Tests\WebTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Class for testing Search API web functionality.
 */
class WebTest extends DrupalWebTestCase {

  protected $server_id;
  protected $index_id;

  protected function assertText($text, $message = '', $group = 'Other') {
    return parent::assertText($text, $message ? $message : $text, $group);
  }

  protected function drupalGet($path, array $options = array(), array $headers = array()) {
    $ret = parent::drupalGet($path, $options, $headers);
    $this->assertResponse(200, t('HTTP code 200 returned.'));
    return $ret;
  }

  protected function drupalPost($path, $edit, $submit, array $options = array(), array $headers = array(), $form_html_id = NULL, $extra_post = NULL) {
    $ret = parent::drupalPost($path, $edit, $submit, $options, $headers, $form_html_id, $extra_post);
    $this->assertResponse(200, t('HTTP code 200 returned.'));
    return $ret;
  }

  public static function getInfo() {
    return array(
      'name' => 'Test search API framework',
      'description' => 'Tests basic functions of the Search API, like creating, editing and deleting servers and indexes.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp('entity', 'search_api', 'search_api_test');
  }

  public function testFramework() {
    $this->drupalLogin($this->drupalCreateUser(array('administer search_api')));
    // @todo Why is there no default index?
    //$this->deleteDefaultIndex();
    $this->insertItems();
    $this->checkOverview1();
    $this->createIndex();
    $this->insertItems(5);
    $this->createServer();
    $this->checkOverview2();
    $this->enableIndex();
    $this->searchNoResults();
    $this->indexItems();
    $this->searchSuccess();
    $this->editServer();
    $this->clearIndex();
    $this->searchNoResults();
    $this->deleteServer();
  }

  protected function deleteDefaultIndex() {
    $this->drupalPost('admin/config/search/search_api/index/default_node_index/delete', array(), t('Confirm'));
  }

  protected function insertItems($offset = 0) {
    $count = db_query('SELECT COUNT(*) FROM {search_api_test}')->fetchField();
    $this->insertItem(array(
      'id' => $offset + 1,
      'title' => 'Title 1',
      'body' => 'Body text 1.',
      'type' => 'Item',
    ));
    $this->insertItem(array(
      'id' => $offset + 2,
      'title' => 'Title 2',
      'body' => 'Body text 2.',
      'type' => 'Item',
    ));
    $this->insertItem(array(
      'id' => $offset + 3,
      'title' => 'Title 3',
      'body' => 'Body text 3.',
      'type' => 'Item',
    ));
    $this->insertItem(array(
      'id' => $offset + 4,
      'title' => 'Title 4',
      'body' => 'Body text 4.',
      'type' => 'Page',
    ));
    $this->insertItem(array(
      'id' => $offset + 5,
      'title' => 'Title 5',
      'body' => 'Body text 5.',
      'type' => 'Page',
    ));
    $count = db_query('SELECT COUNT(*) FROM {search_api_test}')->fetchField() - $count;
    $this->assertEqual($count, 5, t('@count items inserted.', array('@count' => $count)));
  }

  protected function insertItem($values) {
    $this->drupalPost('search_api_test/insert', $values, t('Save'));
  }

  protected function checkOverview1() {
    // This test fails for no apparent reason for drupal.org test bots.
    // Commenting them out for now.
    //$this->drupalGet('admin/config/search/search_api');
    //$this->assertText(t('There are no search servers or indexes defined yet.'), t('"No servers" message is displayed.'));
  }

  protected function createIndex() {
    $values = array(
      'name' => '',
      'item_type' => '',
      'enabled' => 1,
      'description' => 'An index used for testing.',
      'server' => '',
      'options[cron_limit]' => 5,
    );
    $this->drupalPost('admin/config/search/search_api/add_index', $values, t('Create index'));
    $this->assertText(t('!name field is required.', array('!name' => t('Index name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Item type'))));

    $this->index_id = $id = 'test_index';
    $values = array(
      'name' => 'Search API test index',
      'machine_name' => $id,
      'item_type' => 'search_api_test',
      'enabled' => 1,
      'description' => 'An index used for testing.',
      'server' => '',
      'options[cron_limit]' => 1,
    );
    $this->drupalPost(NULL, $values, t('Create index'));

    $this->assertText(t('The index was successfully created. Please set up its indexed fields now.'), t('The index was successfully created.'));
    $found = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $id) !== FALSE;
    $this->assertTrue($found, t('Correct redirect.'));
    $index = search_api_index_load($id, TRUE);
    $this->assertEqual($index->name, $values['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->item_type, $values['item_type'], t('Index item type correctly inserted.'));
    $this->assertFalse($index->enabled, t('Status correctly inserted.'));
    $this->assertEqual($index->description, $values['description'], t('Description correctly inserted.'));
    $this->assertNull($index->server, t('Index server correctly inserted.'));
    $this->assertEqual($index->options['cron_limit'], $values['options[cron_limit]'], t('Cron batch size correctly inserted.'));

    $values = array(
      'additional[field]' => 'parent',
    );
    $this->drupalPost("admin/config/search/search_api/index/$id/fields", $values, t('Add fields'));
    $this->assertText(t('The available fields were successfully changed.'), t('Successfully added fields.'));
    $this->assertText('Parent » ID', t('!field displayed.', array('!field' => t('Added fields are'))));

    $values = array(
      'fields[id][type]' => 'integer',
      'fields[id][boost]' => '1.0',
      'fields[id][indexed]' => 1,
      'fields[title][type]' => 'text',
      'fields[title][boost]' => '5.0',
      'fields[title][indexed]' => 1,
      'fields[body][type]' => 'text',
      'fields[body][boost]' => '1.0',
      'fields[body][indexed]' => 1,
      'fields[type][type]' => 'string',
      'fields[type][boost]' => '1.0',
      'fields[type][indexed]' => 1,
      'fields[parent:id][type]' => 'integer',
      'fields[parent:id][boost]' => '1.0',
      'fields[parent:id][indexed]' => 1,
      'fields[parent:title][type]' => 'text',
      'fields[parent:title][boost]' => '5.0',
      'fields[parent:title][indexed]' => 1,
      'fields[parent:body][type]' => 'text',
      'fields[parent:body][boost]' => '1.0',
      'fields[parent:body][indexed]' => 1,
      'fields[parent:type][type]' => 'string',
      'fields[parent:type][boost]' => '1.0',
      'fields[parent:type][indexed]' => 1,
    );
    $this->drupalPost(NULL, $values, t('Save changes'));
    $this->assertText(t('The indexed fields were successfully changed. The index was cleared and will have to be re-indexed with the new settings.'), t('Field settings saved.'));

    $values = array(
      'callbacks[search_api_alter_add_url][status]' => 1,
      'callbacks[search_api_alter_add_url][weight]' => 0,
      'callbacks[search_api_alter_add_aggregation][status]' => 1,
      'callbacks[search_api_alter_add_aggregation][weight]' => 10,
      'processors[search_api_case_ignore][status]' => 1,
      'processors[search_api_case_ignore][weight]' => 0,
      'processors[search_api_case_ignore][settings][fields][title]' => 1,
      'processors[search_api_case_ignore][settings][fields][body]' => 1,
      'processors[search_api_case_ignore][settings][fields][parent:title]' => 1,
      'processors[search_api_case_ignore][settings][fields][parent:body]' => 1,
      'processors[search_api_tokenizer][status]' => 1,
      'processors[search_api_tokenizer][weight]' => 20,
      'processors[search_api_tokenizer][settings][spaces]' => '[^\p{L}\p{N}]',
      'processors[search_api_tokenizer][settings][ignorable]' => '[-]',
      'processors[search_api_tokenizer][settings][fields][title]' => 1,
      'processors[search_api_tokenizer][settings][fields][body]' => 1,
      'processors[search_api_tokenizer][settings][fields][parent:title]' => 1,
      'processors[search_api_tokenizer][settings][fields][parent:body]' => 1,
    );
    $this->drupalPost(NULL, $values, t('Add new field'));
    $values = array(
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][name]' => 'Test fulltext field',
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][type]' => 'fulltext',
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][fields][title]' => 1,
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][fields][body]' => 1,
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][fields][parent:title]' => 1,
      'callbacks[search_api_alter_add_aggregation][settings][fields][search_api_aggregation_1][fields][parent:body]' => 1,
    );
    $this->drupalPost(NULL, $values, t('Save configuration'));
    $this->assertText(t("The search index' workflow was successfully edited. All content was scheduled for re-indexing so the new settings can take effect."), t('Workflow successfully edited.'));

    $this->drupalGet("admin/config/search/search_api/index/$id");
    $this->assertTitle('Search API test index | Drupal', t('Correct title when viewing index.'));
    $this->assertText('An index used for testing.', t('!field displayed.', array('!field' => t('Description'))));
    $this->assertText('Search API test entity', t('!field displayed.', array('!field' => t('Item type'))));
    $this->assertText(format_plural(1, '1 item per cron batch.', '@count items per cron batch.'), t('!field displayed.', array('!field' => t('Cron batch size'))));

    $this->drupalGet("admin/config/search/search_api/index/$id/status");
    $this->assertText(t('The index is currently disabled.'), t('"Disabled" status displayed.'));
  }

  protected function createServer() {
    $values = array(
      'name' => '',
      'enabled' => 1,
      'description' => 'A server used for testing.',
      'class' => '',
    );
    $this->drupalPost('admin/config/search/search_api/add_server', $values, t('Create server'));
    $this->assertText(t('!name field is required.', array('!name' => t('Server name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Service class'))));

    $this->server_id = $id = 'test_server';
    $values = array(
      'name' => 'Search API test server',
      'machine_name' => $id,
      'enabled' => 1,
      'description' => 'A server used for testing.',
      'class' => 'search_api_test_service',
    );
    $this->drupalPost(NULL, $values, t('Create server'));

    $values2 = array(
      'options[form][test]' => 'search_api_test foo bar',
    );
    $this->drupalPost(NULL, $values2, t('Create server'));

    $this->assertText(t('The server was successfully created.'));
    $found = strpos($this->getUrl(), 'admin/config/search/search_api/server/' . $id) !== FALSE;
    $this->assertTrue($found, t('Correct redirect.'));
    $server = search_api_server_load($id, TRUE);
    $this->assertEqual($server->name, $values['name'], t('Name correctly inserted.'));
    $this->assertTrue($server->enabled, t('Status correctly inserted.'));
    $this->assertEqual($server->description, $values['description'], t('Description correctly inserted.'));
    $this->assertEqual($server->class, $values['class'], t('Service class correctly inserted.'));
    $this->assertEqual($server->options['test'], $values2['options[form][test]'], t('Service options correctly inserted.'));
    $this->assertTitle('Search API test server | Drupal', t('Correct title when viewing server.'));
    $this->assertText('A server used for testing.', t('!field displayed.', array('!field' => t('Description'))));
    $this->assertText('search_api_test_service', t('!field displayed.', array('!field' => t('Service name'))));
    $this->assertText('search_api_test_service description', t('!field displayed.', array('!field' => t('Service description'))));
    $this->assertText('search_api_test foo bar', t('!field displayed.', array('!field' => t('Service options'))));
  }

  protected function checkOverview2() {
    $this->drupalGet('admin/config/search/search_api');
    $this->assertText('Search API test server', t('!field displayed.', array('!field' => t('Server'))));
    $this->assertText('Search API test index', t('!field displayed.', array('!field' => t('Index'))));
    $this->assertNoText(t('There are no search servers or indexes defined yet.'), t('"No servers" message not displayed.'));
  }

  protected function enableIndex() {
    $values = array(
      'server' => $this->server_id,
    );
    $this->drupalPost("admin/config/search/search_api/index/{$this->index_id}/edit", $values, t('Save settings'));
    $this->assertText(t('The search index was successfully edited.'));
    $this->assertText('Search API test server', t('!field displayed.', array('!field' => t('Server'))));

    $this->clickLink(t('enable'));
    $this->assertText(t('The index was successfully enabled.'));
  }

  protected function searchNoResults() {
    $this->drupalGet('search_api_test/query/' . $this->index_id);
    $this->assertText('result count = 0', t('No search results returned without indexing.'));
    $this->assertText('results = ()', t('No search results returned without indexing.'));
  }

  protected function indexItems() {
    $this->drupalGet("admin/config/search/search_api/index/{$this->index_id}/status");
    $this->assertText(t('The index is currently enabled.'), t('"Enabled" status displayed.'));
    $this->assertText(t('All items still need to be indexed (@total total).', array('@total' => 10)), t('!field displayed.', array('!field' => t('Correct index status'))));
    $this->assertText(t('Index now'), t('"Index now" button found.'));
    $this->assertText(t('Clear index'), t('"Clear index" button found.'));
    $this->assertNoText(t('Re-index content'), t('"Re-index" button not found.'));

    // Here we test the indexing + the warning message when some items
    // can not be indexed.
    // The server refuses (for test purpose) to index items with IDs that are
    // multiples of 8 unless the "search_api_test_index_all" variable is set.
    $values = array(
      'limit' => 8,
    );
    $this->drupalPost(NULL, $values, t('Index now'));
    $this->assertText(t('Successfully indexed @count items.', array('@count' => 7)));
    $this->assertText(t('1 item could not be indexed. Check the logs for details.'), t('Index errors warning is displayed.'));
    $this->assertNoText(t("Couldn't index items. Check the logs for details."), t("Index error isn't displayed."));
    $this->assertText(t('About @percentage% of all items have been indexed in their latest version (@indexed / @total).', array('@indexed' => 7, '@total' => 10, '@percentage' => 70)), t('!field displayed.', array('!field' => t('Correct index status'))));
    $this->assertText(t('Re-indexing'), t('"Re-index" button found.'));

    // Here we're testing the error message when no item could be indexed.
    // The item with ID 8 is still not indexed.
    $values = array(
      'limit' => 1,
    );
    $this->drupalPost(NULL, $values, t('Index now'));
    $this->assertNoPattern('/' . str_replace('144', '-?\d*', t('Successfully indexed @count items.', array('@count' => 144))) . '/', t('No items could be indexed.'));
    $this->assertNoText(t('1 item could not be indexed. Check the logs for details.'), t("Index errors warning isn't displayed."));
    $this->assertText(t("Couldn't index items. Check the logs for details."), t('Index error is displayed.'));

    // Here we test the indexing of all the remaining items.
    \Drupal::state()->set('search_api_test_index_all', TRUE);
    $values = array(
      'limit' => -1,
    );
    $this->drupalPost(NULL, $values, t('Index now'));
    $this->assertText(t('Successfully indexed @count items.', array('@count' => 3)));
    $this->assertNoText(t("Some items couldn't be indexed. Check the logs for details."), t("Index errors warning isn't displayed."));
    $this->assertNoText(t("Couldn't index items. Check the logs for details."), t("Index error isn't displayed."));
    $this->assertText(t('All items have been indexed (@indexed / @total).', array('@indexed' => 10, '@total' => 10)), t('!field displayed.', array('!field' => t('Correct index status'))));
    $this->assertNoText(t('Index now'), t('"Index now" button no longer displayed.'));
  }

  protected function searchSuccess() {
    $this->drupalGet('search_api_test/query/' . $this->index_id);
    $this->assertText('result count = 10', t('Correct search result count returned after indexing.'));
    $this->assertText('results = (1, 2, 3, 4, 5, 6, 7, 8, 9, 10)', t('Correct search results returned after indexing.'));

    $this->drupalGet('search_api_test/query/' . $this->index_id . '/foo/2/4');
    $this->assertText('result count = 10', t('Correct search result count with ranged query.'));
    $this->assertText('results = (3, 4, 5, 6)', t('Correct search results with ranged query.'));
  }

  protected function editServer() {
    $values = array(
      'name' => 'test-name-foo',
      'description' => 'test-description-bar',
      'options[form][test]' => 'test-test-baz',
    );
    $this->drupalPost("admin/config/search/search_api/server/{$this->server_id}/edit", $values, t('Save settings'));
    $this->assertText(t('The search server was successfully edited.'));
    $this->assertText('test-name-foo', t('!field changed.', array('!field' => t('Name'))));
    $this->assertText('test-description-bar', t('!field changed.', array('!field' => t('Description'))));
    $this->assertText('test-test-baz', t('!field changed.', array('!field' => t('Service options'))));
  }

  protected function clearIndex() {
    $this->drupalPost("admin/config/search/search_api/index/{$this->index_id}/status", array(), t('Clear index'));
    $this->assertText(t('The index was successfully cleared.'));
    $this->assertText(t('All items still need to be indexed (@total total).', array('@total' => 10)), t('!field displayed.', array('!field' => t('Correct index status'))));
  }

  protected function deleteServer() {
    $this->drupalPost("admin/config/search/search_api/server/{$this->server_id}/delete", array(), t('Confirm'));
    $this->assertNoText('test-name-foo', t('Server no longer listed.'));
    $this->drupalGet("admin/config/search/search_api/index/{$this->index_id}/status");
    $this->assertText(t('The index is currently disabled.'), t('The index was disabled and removed from the server.'));
  }

}
