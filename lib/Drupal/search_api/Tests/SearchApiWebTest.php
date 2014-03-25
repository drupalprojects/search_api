<?php
/**
 * @file
 * Definition of \Drupal\search_api\Tests\search_apiTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides the web tests for Search API.
 */
class SearchApiWebTest extends WebTestBase {

  public static $modules = array('search_api', 'node');

  protected $testUser;
  protected $serverId;
  protected $indexId;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API web tests',
      'description' => 'Tests for Search API to see if the interface reacts as it should.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp();

    // Create user with Search API permissions.
    $this->test_user = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
  }

  public function testFramework() {

    $this->drupalLogin($this->testUser);
    $this->renderMenuLinkTest();
    $this->createServer();
    $this->createIndex();
    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
    $this->addFiltersToIndex();
  }

  public function renderMenuLinkTest() {
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');
  }

  public function createServer() {

    $settings_path = 'admin/config/search/search_api/add_server';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => '',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Server name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Service'))));

    $edit = array(
      'name' => 'Search API test server',
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => 'search_api_test_service',
    );
    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Machine-readable name'))));

    $this->server_id = 'test_server';

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => 'search_api_test_service',
    );

    // The first post gives a 'Please configure the used service.' warning,
    // so we have to submit the form twice.
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The server was successfully saved.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/server/' . $this->server_id) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect.'));
  }

  public function createIndex() {

    $settings_path = 'admin/config/search/search_api/add_index';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'An index used for testing.',
      'datasourcePluginId' => '',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Index name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Datasource'))));

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'datasourcePluginId' => 'search_api_content_entity_datasource:node',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The index was successfully saved.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to index page.'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);

    $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
    $this->assertTrue($index->status, t('Index status correctly inserted.'));
    $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
    $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
    $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));
  }

  public function addFieldsToIndex() {
    $settings_path = 'admin/config/search/search_api/index/' . $this->indexId . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[entity:node:nid][indexed]' => 1,
      'fields[entity:node:title][indexed]' => 1,
      'fields[entity:node:title][type]' => 'text',
      'fields[entity:node:title][boost]' => '21.0',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save changes'));
    $this->assertText(t('The indexed fields were successfully changed. The index was cleared and will have to be re-indexed with the new settings.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId . '/fields') !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();

    $this->assertEqual($fields['entity:node:nid']['indexed'], $edit['fields[entity:node:nid][indexed]'], t('nid field is indexed.'));
    $this->assertEqual($fields['entity:node:title']['indexed'], $edit['fields[entity:node:title][indexed]'], t('title field is indexed.'));
    $this->assertEqual($fields['entity:node:title']['type'], $edit['fields[entity:node:title][type]'], t('title field type is text.'));
    $this->assertEqual($fields['entity:node:title']['boost'], $edit['fields[entity:node:title][boost]'], t('title field boost value is 21.'));
  }

  public function addAdditionalFieldsToIndex() {
    // @todo Implement addAdditionalFieldsToIndex() method.
  }

  public function addFiltersToIndex() {
    $settings_path = 'admin/config/search/search_api/index/' . $this->indexId . '/filters';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'processors[search_api_highlight_processor][status]' => 1,
      'processors[search_api_add_aggregation_processor][status]' => 1,
    );

    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('The indexing workflow was successfully edited. All content was scheduled for re-indexing so the new settings can take effect.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId . '/filters') !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));
  }
}
