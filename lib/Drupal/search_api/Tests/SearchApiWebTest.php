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

    protected $test_user;
    protected $server_id;

    /**
     * {@inheritdoc}
     */
    public static function getInfo() {
        return array(
            'name' => 'Search API web tests',
            'description' => 'Tests for Search API to see if the interface reacts as it should..',
            'group' => 'Search API',
        );
    }

    public function setUp() {
        parent::setUp();

        // Create user with Search API permissions.
        $this->test_user = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    }

    public function testFramework() {

        $this->drupalLogin($this->test_user);
        $this->renderMenuLinkTest();
        $this->createServer();
        $this->createIndex();
        $this->addFieldsToIndex();
        $this->addAdditionalFieldsToIndex();
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
            'machine_name' => $this->server_id,
            'status' => 1,
            'description' => 'A server used for testing.',
            'servicePluginId' => 'search_api_test_service',
        );

        // The first post gives a 'Please configure the used service.' warning, so we have to submit the form twice.
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

        $this->index_id = 'test_index';

        $edit = array(
            'name' => 'Search API test index',
            'machine_name' => $this->index_id,
            'status' => 1,
            'description' => 'An index used for testing.',
            'serverMachineName' => $this->server_id,
            'datasourcePluginId' => 'search_api_content_entity_datasource:node',
        );

        $this->drupalPostForm(NULL, $edit, t('Save'));
        $this->drupalPostForm(NULL, $edit, t('Save'));

        $this->assertText(t('The index was successfully saved.'));
        $redirect_path = strpos($this->getUrl(), 'admin/config/search/search_api/index/' . $this->index_id) !== FALSE;
        $this->assertTrue($redirect_path, t('Correct redirect to index page.'));

        $index = entity_load('search_api_index', $this->index_id, TRUE);

        $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
        $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
        $this->assertTrue($index->status, t('Index status correctly inserted.'));
        $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
        $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
        $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));
    }

    public function addFieldsToIndex() {
        $settings_path = 'admin/config/search/search_api/index/' . $this->index_id . '/fields';

        $this->drupalGet($settings_path);
        $this->assertResponse(200);

        $edit = array(
            'fields[nid][indexed]' => 1,
            'fields[vid][indexed]' => 0,
            'fields[is_new][indexed]' => 1,
        );

        $this->drupalPostForm($settings_path, $edit, t('Save changes'));
        $index = entity_load('search_api_index', $this->index_id, TRUE);
        $fields = $index->getFields();

        $this->assertEqual($fields['fields']['vid']['indexed'], $edit['fields[vid][indexed]'], t('vid field is not indexed.'));
        $this->assertEqual($fields['fields']['nid']['indexed'], $edit['fields[nid][indexed]'], t('nid field is indexed.'));
        $this->assertEqual($fields['fields']['is_new']['indexed'], $edit['fields[is_new][indexed]'], t('is_new field is indexed.'));
    }

    public function addAdditionalFieldsToIndex() {
        // @todo Implement addAdditionalFieldsToIndex() method.
    }

    public function renderMenuLinkTest() {
      $this->drupalGet('admin/config');
      $this->assertText('Search API', 'Search API menu link is displayed.');
    }
}


