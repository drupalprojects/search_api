<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiIntegrationTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Provides integration tests for Search API.
 */
class SearchApiIntegrationTest extends SearchApiWebTestBase {

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API integration test',
      'description' => 'Test creation of Search API indexes and servers through the UI.',
      'group' => 'Search API',
    );
  }

  /**
   * Tests various UI interactions between servers and indexes.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');

    $this->createServer();
    $this->createIndex();
    $this->trackContent();

    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
  }

  protected function createServer() {
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

  protected function createIndex() {
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

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'datasourcePluginId' => 'entity:node',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The index was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/index/' . $this->indexId, array(), t('Correct redirect to index page.'));

    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    $this->assertEqual($index->name, $edit['name'], t('Name correctly inserted.'));
    $this->assertEqual($index->machine_name, $edit['machine_name'], t('Index machine name correctly inserted.'));
    $this->assertTrue($index->status, t('Index status correctly inserted.'));
    $this->assertEqual($index->description, $edit['description'], t('Index machine name correctly inserted.'));
    $this->assertEqual($index->serverMachineName, $edit['serverMachineName'], t('Index server machine name correctly inserted.'));
    $this->assertEqual($index->datasourcePluginId, $edit['datasourcePluginId'], t('Index datasource id correctly inserted.'));
  }

  protected function addFieldsToIndex() {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[nid][indexed]' => 1,
      'fields[title][indexed]' => 1,
      'fields[title][type]' => 'text',
      'fields[title][boost]' => '21.0',
    );

    $this->drupalPostForm($settings_path, $edit, t('Save changes'));
    $this->assertText(t('The indexed fields were successfully changed. The index was cleared and will have to be re-indexed with the new settings.'));
    $this->assertUrl($settings_path, array(), t('Correct redirect to fields page.'));

    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();

    $this->assertEqual($fields['nid']['indexed'], $edit['fields[nid][indexed]'], t('nid field is indexed.'));
    $this->assertEqual($fields['title']['indexed'], $edit['fields[title][indexed]'], t('title field is indexed.'));
    $this->assertEqual($fields['title']['type'], $edit['fields[title][type]'], t('title field type is text.'));
    $this->assertEqual($fields['title']['boost'], $edit['fields[title][boost]'], t('title field boost value is 21.'));
  }

  protected function addAdditionalFieldsToIndex() {
    // @todo Implement addAdditionalFieldsToIndex() method.
  }

  protected function trackContent() {
    // Initially there should be no tracked items, because there are no nodes
    $tracked_items = $this->countTrackedItems();

    $this->assertEqual($tracked_items, 0, t('No items are tracked yet'));

    // Add two articles and a page
    $article1 = $this->drupalCreateNode(array('type' => 'article'));
    $article2 = $this->drupalCreateNode(array('type' => 'article'));
    $page1 = $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the index immediately
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, t('Three items are tracked'));

    // Create the edit index path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';

    // Test disabling the index
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasourcePluginConfig[default]' => 0,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, t('No items are tracked'));

    // Debug it
    $this->verbose($this->drupalGet($settings_path));

    // Test enabling the index
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 0,
      'datasourcePluginConfig[bundles][article]' => TRUE,
      'datasourcePluginConfig[bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, t('Three items are tracked'));

    // Debug it
    $this->verbose($this->drupalGet($settings_path));

    // Test putting default to zero and no bundles checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all the items should get deleted.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasourcePluginConfig[default]' => 0,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, t('No items are tracked'));

    // Test putting default to zero and the article bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 0,
      'datasourcePluginConfig[bundles][article]' => TRUE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->verbose($this->drupalGet($settings_path));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, t('Two items are tracked'));

    // Test putting default to zero and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 0,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, t('One items is tracked'));

    // Test putting default to one and the article bundle checked.
    // This will add all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 1,
      'datasourcePluginConfig[bundles][article]' => TRUE,
      'datasourcePluginConfig[bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, t('One item is tracked'));

    // Test putting default to one and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasourcePluginConfig[default]' => 1,
      'datasourcePluginConfig[bundles][article]' => FALSE,
      'datasourcePluginConfig[bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, t('Two items are tracked'));

    // Now lets delete an article. That should remove one item from the item
    // table
    $article1->delete();

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, t('One item is tracked'));
  }

  /**
   * Counts the number of tracked items from an index.
   *
   * @return int
   */
  protected function countTrackedItems() {
    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getDatasource()->getTotalItemsCount();
  }

}
