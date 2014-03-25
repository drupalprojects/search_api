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

<<<<<<< HEAD
  protected $testUser;
  protected $serverId;
  protected $indexId;
=======
  protected $test_user;
  protected $server_id;
>>>>>>> Small fixes for the processors

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
<<<<<<< HEAD

    $this->drupalLogin($this->testUser);
=======
    $this->drupalLogin($this->test_user);
>>>>>>> Small fixes for the processors
    $this->renderMenuLinkTest();
    $this->createServer();
    $this->createIndex();
    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
    $this->addFiltersToIndex();
  }

<<<<<<< HEAD
  public function renderMenuLinkTest() {
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');
  }

  public function createServer() {

=======
  public function createServer() {
>>>>>>> Small fixes for the processors
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

<<<<<<< HEAD
=======

>>>>>>> Small fixes for the processors
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
<<<<<<< HEAD
      'machine_name' => $this->serverId,
=======
      'machine_name' => $this->server_id,
>>>>>>> Small fixes for the processors
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => 'search_api_test_service',
    );

<<<<<<< HEAD
    // The first post gives a 'Please configure the used service.' warning,
    // so we have to submit the form twice.
=======
    // The first post gives a 'Please configure the used service.' warning, so we have to submit the form twice.
>>>>>>> Small fixes for the processors
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The server was successfully saved.'));
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/server/' . $this->server_id) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect.'));
  }

  public function createIndex() {
<<<<<<< HEAD

=======
>>>>>>> Small fixes for the processors
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

<<<<<<< HEAD
    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
=======
    $this->index_id = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->index_id,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->server_id,
>>>>>>> Small fixes for the processors
      'datasourcePluginId' => 'search_api_content_entity_datasource:node',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The index was successfully saved.'));
<<<<<<< HEAD
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to index page.'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
=======
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->index_id) !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to index page.'));

    $index = entity_load('search_api_index', $this->index_id, TRUE);
>>>>>>> Small fixes for the processors

    $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
    $this->assertTrue($index->status, t('Index status correctly inserted.'));
    $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
    $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
    $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));
  }

  public function addFieldsToIndex() {
<<<<<<< HEAD
    $settings_path = 'admin/config/search/search_api/index/' . $this->indexId . '/fields';
=======
    $settings_path = 'admin/config/search/search_api/index/' . $this->index_id . '/fields';
>>>>>>> Small fixes for the processors

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
<<<<<<< HEAD
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId . '/fields') !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
=======
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->index_id . '/fields') !== FALSE;
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));

    $index = entity_load('search_api_index', $this->index_id, TRUE);
>>>>>>> Small fixes for the processors
    $fields = $index->getFields();

    $this->assertEqual($fields['entity:node:nid']['indexed'], $edit['fields[entity:node:nid][indexed]'], t('nid field is indexed.'));
    $this->assertEqual($fields['entity:node:title']['indexed'], $edit['fields[entity:node:title][indexed]'], t('title field is indexed.'));
    $this->assertEqual($fields['entity:node:title']['type'], $edit['fields[entity:node:title][type]'], t('title field type is text.'));
    $this->assertEqual($fields['entity:node:title']['boost'], $edit['fields[entity:node:title][boost]'], t('title field boost value is 21.'));
  }

  public function addAdditionalFieldsToIndex() {
    // @todo Implement addAdditionalFieldsToIndex() method.
  }

<<<<<<< HEAD
  public function addFiltersToIndex() {
    $settings_path = 'admin/config/search/search_api/index/' . $this->indexId . '/filters';
=======
  public function renderMenuLinkTest() {
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');
  }

  public function addFiltersToIndex() {
    $settings_path = 'admin/config/search/search_api/index/' . $this->index_id . '/filters';
>>>>>>> Small fixes for the processors

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'processors[search_api_highlight_processor][status]' => 1,
      'processors[search_api_add_aggregation_processor][status]' => 1,
    );

    $this->drupalPostForm($settings_path, $edit, t('Save'));
    $this->assertText(t('The indexing workflow was successfully edited. All content was scheduled for re-indexing so the new settings can take effect.'));
<<<<<<< HEAD
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->indexId . '/filters') !== FALSE;
=======
    $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->index_id . '/filters') !== FALSE;
>>>>>>> Small fixes for the processors
    $this->assertTrue($redirect_path, t('Correct redirect to fields page.'));
  }
}
