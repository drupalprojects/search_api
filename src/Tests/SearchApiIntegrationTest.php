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

    // Test that the overview page exists and its permissions work.
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');

    $this->drupalGet('admin/config/search/search-api');
    $this->assertResponse(200, 'Admin user can access the overview page.');

    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('admin/config/search/search-api');
    $this->assertResponse(403, "User without permissions doesn't have access to the overview page.");

    // Login as an admin user for the rest of the tests.
    $this->drupalLogin($this->adminUser);

    $this->createServer();
    $this->createIndex();
    $this->trackContent();

    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
  }

  protected function createServer() {
    $settings_path = $this->urlGenerator->generateFromRoute('search_api.server_add', array(), array('absolute' => TRUE));

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
    $settings_path = $this->urlGenerator->generateFromRoute('search_api.index_add', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'status' => 1,
      'description' => 'An index used for testing.',
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('!name field is required.', array('!name' => t('Index name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Machine-readable name'))));
    $this->assertText(t('!name field is required.', array('!name' => t('Data types'))));

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'datasourcePluginIds[]' => array('entity:node'),
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
    $this->assertEqual($index->datasourcePluginIds, $edit['datasourcePluginIds[]'], t('Index datasource id correctly inserted.'));
  }

  protected function addFieldsToIndex() {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[entity:node|nid][indexed]' => 1,
      'fields[entity:node|title][indexed]' => 1,
      'fields[entity:node|title][type]' => 'text',
      'fields[entity:node|title][boost]' => '21.0',
      'fields[entity:node|body][indexed]' => 1,
    );

    $this->drupalPostForm($settings_path, $edit, t('Save changes'));
    $this->assertText(t('The indexed fields were successfully changed. The index was cleared and will have to be re-indexed with the new settings.'));

    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();

    $this->assertEqual($fields['entity:node|nid']['indexed'], $edit['fields[entity:node|nid][indexed]'], t('nid field is indexed.'));
    $this->assertEqual($fields['entity:node|title']['indexed'], $edit['fields[entity:node|title][indexed]'], t('title field is indexed.'));
    $this->assertEqual($fields['entity:node|title']['type'], $edit['fields[entity:node|title][type]'], t('title field type is text.'));
    $this->assertEqual($fields['entity:node|title']['boost'], $edit['fields[entity:node|title][boost]'], t('title field boost value is 21.'));

    // Check that a 'parent_data_type.data_type' Search API field type => data
    // type mapping relationship works.
    $this->assertEqual($fields['entity:node|body']['type'], 'text', 'Complex field mapping relationship works.');
  }

  protected function addAdditionalFieldsToIndex() {
    // Test that an entity reference field which targets a content entity is
    // shown.
    $this->assertFieldByName('additional[field][entity:node|uid]', NULL, 'Additional entity reference field targeting a content entity type is displayed.');

    // Test that an entity reference field which targets a config entity is not
    // shown as an additional field option.
    $this->assertNoFieldByName('additional[field][entity:node|type]', NULL,'Additional entity reference field targeting a config entity type is not displayed.');

    // @todo Implement more tests for additional fields.
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
      'datasourcePluginConfigs[entity:node][default]' => 0,
      'datasourcePluginConfigs[entity:node][bundles][article]' => FALSE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => FALSE,
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
      'datasourcePluginConfigs[entity:node][default]' => 0,
      'datasourcePluginConfigs[entity:node][bundles][article]' => TRUE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => TRUE,
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
      'datasourcePluginConfigs[entity:node][default]' => 0,
      'datasourcePluginConfigs[entity:node][bundles][article]' => FALSE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => FALSE,
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
      'datasourcePluginConfigs[entity:node][default]' => 0,
      'datasourcePluginConfigs[entity:node][bundles][article]' => TRUE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => FALSE,
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
      'datasourcePluginConfigs[entity:node][default]' => 0,
      'datasourcePluginConfigs[entity:node][bundles][article]' => FALSE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => TRUE,
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
      'datasourcePluginConfigs[entity:node][default]' => 1,
      'datasourcePluginConfigs[entity:node][bundles][article]' => TRUE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => FALSE,
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
      'datasourcePluginConfigs[entity:node][default]' => 1,
      'datasourcePluginConfigs[entity:node][bundles][article]' => FALSE,
      'datasourcePluginConfigs[entity:node][bundles][page]' => TRUE,
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
    return $index->getTracker()->getTotalItemsCount();
  }

}
