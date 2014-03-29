<?php
/**
 * @file
 * Definition of \Drupal\search_api\Tests\SearchApiWebTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Provides the web tests for Search API.
 */
class SearchApiWebTest extends SearchApiWebTestBase {

  protected $serverId;
  protected $indexId;

  protected $article1;
  protected $article2;

  protected $page1;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API web tests',
      'description' => 'Test creation of Search API indexes en servers through the UI.',
      'group' => 'Search API',
    );
  }

  public function testFramework() {

    $this->drupalLogin($this->adminUser);
    $this->renderMenuLinkTest();

    $this->createServer();
    $this->createIndex();
    $this->trackContent();

    //$this->addFieldsToIndex();
    //$this->addAdditionalFieldsToIndex();
  }

  public function createServer() {
    $settings_path = $this->urlGenerator->generateFromRoute('search_api.server_add');

    $this->drupalGet($settings_path);
    $this->assertResponse(200, 'Server add page exists');

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

    $this->serverId = 'test_server';

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'servicePluginId' => 'search_api_test_service',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The server was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/server/' . $this->serverId, array(), t('Correct redirect to server page.'));
  }

  public function createIndex() {
    $settings_path = $this->urlGenerator->generateFromRoute('search_api.index_add');

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'An index used for testing.',
      'datasourcePluginId' => '',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Index name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Data type'))));

    $this->index_id = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->index_id,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'datasourcePluginId' => 'entity:node',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The index was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/index/' . $this->index_id, array(), t('Correct redirect to index page.'));

    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->index_id, TRUE);

    $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
    $this->assertTrue($index->status, t('Index status correctly inserted.'));
    $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
    $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
    $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));
  }

  public function addFieldsToIndex() {
    $settings_path = 'admin/config/search/search-api/index/' . $this->index_id . '/fields';

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
    $this->assertUrl($settings_path, array(), t('Correct redirect to fields page.'));

    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->index_id, TRUE);
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

  public function trackContent() {
    // Intially there should be no tracked items, because there are no nodes
    $tracked_items = $this->countTrackedItems();
    debug($tracked_items);

    $this->assertEqual($tracked_items, 0, t('No items are tracked yet'));

    // Add two articles and a page
    $article1 = $this->drupalCreateNode(array('type' => 'article'));
    $article2 = $this->drupalCreateNode(array('type' => 'article'));
    $page1 = $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the index immediately
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, t('Three items are tracked'));

    // Create the edit index path
    $settings_path = 'admin/config/search/search-api/index/' . $this->index_id . '/edit';

    // Test disabling the index
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasourcePluginConfig[default]' => 1,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, t('No items are tracked'));

    // Test enabling the index
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 1,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, t('Three items are tracked'));
  }

  private function countTrackedItems() {
    $results = db_select('search_api_item', 'sai')
      ->fields('sai')
      ->execute()
      ->fetchAllKeyed();

    return count($results);
  }
}
