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

  protected $urlGenerator;

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
    $this->testUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->urlGenerator = $this->container->get('url_generator');
  }

  public function testFramework() {
    $this->drupalLogin($this->testUser);
    $this->renderMenuLinkTest();
    $server = $this->createServer();
    $index = $this->createIndex($server);
    $this->addFieldsToIndex($index);
    $this->addAdditionalFieldsToIndex();
  }

  public function createServer() {
    $settings_path = 'admin/config/search/search-api/add-server';

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

    $serverId = 'test_server';

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => 'search_api_test_service',
    );

    // The first post gives a 'Please configure the used service.' warning,
    // so we have to submit the form twice.
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The server was successfully saved.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search-api/server/' . $this->serverId) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect.'));

    return entity_load('search_api_server', $edit['machine_name'], TRUE);
  }

  public function createIndex($server) {

    $settings_path = 'admin/config/search/search-api/add-index';

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

    $indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $server->id(),
      'datasourcePluginId' => 'search_api_content_entity_datasource:node',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The index was successfully saved.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search-api/index/' . $indexId) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to index page.'));

    $index = entity_load('search_api_index', $indexId, TRUE);

    $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
    $this->assertTrue($index->status, t('Index status correctly inserted.'));
    $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
    $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
    $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));

    return $index;
  }

  public function addFieldsToIndex($index) {
    $settings_path = 'admin/config/search/search-api/index/' . $index->id() . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[node:nid][indexed]' => 1,
      'fields[node:title][indexed]' => 1,
      'fields[node:title][type]' => 'text',
      'fields[node:title][boost]' => '21.0',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save changes'));
    $this->assertText(t('The indexed fields were successfully changed. The index was cleared and will have to be re-indexed with the new settings.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search-api/index/' . $index->id() . '/filters') !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));

    $index = entity_load('search_api_index', $index->id(), TRUE);
    $fields = $index->getFields();

    $this->assertEqual($fields['node:nid']['indexed'], $edit['fields[node:nid][indexed]'], t('nid field is indexed.'));
    $this->assertEqual($fields['node:title']['indexed'], $edit['fields[node:title][indexed]'], t('title field is indexed.'));
    $this->assertEqual($fields['node:title']['type'], $edit['fields[node:title][type]'], t('title field type is text.'));
    $this->assertEqual($fields['node:title']['boost'], $edit['fields[node:title][boost]'], t('title field boost value is 21.'));
  }

  public function addAdditionalFieldsToIndex() {
    // @todo Implement addAdditionalFieldsToIndex() method.
  }

  public function renderMenuLinkTest() {
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');
  }
}
