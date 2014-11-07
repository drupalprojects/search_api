<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\IntegrationTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Tests the overall functionality of the Search API framework and UI.
 *
 * @group search_api
 */
class IntegrationTest extends IntegrationTestBase {

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

    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
    $this->removeFieldsFromIndex();

    $this->addFilter('ignorecase');
    $this->configureFilterFields();

    $this->setReadOnly();
    $this->disableEnableIndex();
    $this->changeIndexDatasource();
    $this->changeIndexServer();

  }

  protected function addFieldsToIndex() {
    $settings_path = $this->getIndexPath($this->indexId) . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[entity:node|nid][indexed]' => 1,
      'fields[entity:node|title][indexed]' => 1,
      'fields[entity:node|title][type]' => 'text',
      'fields[entity:node|title][boost]' => '21.0',
      'fields[entity:node|body][indexed]' => 1,
    );

    $this->drupalPostForm($settings_path, $edit, $this->t('Save changes'));
    $this->assertText($this->t('The changes were successfully saved.'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields(FALSE);

    $this->assertEqual($fields['entity:node|nid']->isIndexed(), $edit['fields[entity:node|nid][indexed]'], $this->t('nid field is indexed.'));
    $this->assertEqual($fields['entity:node|title']->isIndexed(), $edit['fields[entity:node|title][indexed]'], $this->t('title field is indexed.'));
    $this->assertEqual($fields['entity:node|title']->getType(), $edit['fields[entity:node|title][type]'], $this->t('title field type is text.'));
    $this->assertEqual($fields['entity:node|title']->getBoost(), $edit['fields[entity:node|title][boost]'], $this->t('title field boost value is 21.'));

    // Check that a 'parent_data_type.data_type' Search API field type => data
    // type mapping relationship works.
    $this->assertEqual($fields['entity:node|body']->getType(), 'text', 'Complex field mapping relationship works.');
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

  protected function removeFieldsFromIndex() {
    $edit = array(
      'fields[entity:node|body][indexed]' => FALSE,
    );
    $this->drupalPostForm($this->getIndexPath($this->indexId) . '/fields', $edit, $this->t('Save changes'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();
    $this->assertTrue(!isset($fields['entity:node|body']), 'The body field has been removed from the index.');
  }

  /**
   * Sets an index to read only and checks if it reacts accordingly.
   *
   * The expected behavior is such that when an index is set to Read Only it
   * keeps tracking but when it comes to indexing it does not proceed to send
   * items to the server.
   */
  protected function setReadOnly() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'read_only' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    // This should have 2 items in the index

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $remaining_before = $this->countRemainingItems();

    $index_path = 'admin/config/search/search-api/index/' . $this->indexId;
    $this->drupalGet($index_path);

    $this->assertNoText($this->t('Index now'), $this->t("Making sure that the Index now button does not appear in the UI after setting the index to read_only"));

    // Let's index using the API also to make sure we can't index
    $index->index();

    $remaining_after = $this->countRemainingItems();
    $this->assertEqual($remaining_before, $remaining_after, $this->t('No items were indexed after setting to read_only'));

    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);

    $edit = array(
      'read_only' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $remaining_before = $index->getTracker()->getRemainingItemsCount();

    $this->drupalGet($index_path);
    $this->drupalPostForm(NULL, array(), $this->t('Index now'));

    $remaining_after = $index->getTracker()->getRemainingItemsCount();
    $this->assertNotEqual($remaining_before, $remaining_after, $this->t('Items were indexed after removing the read_only flag'));

  }

  /**
   * Disables and enables an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that when an index is disabled, all the items
   * from this index in the tracker are removed and it also tells the backend
   * to remove all items from this index.
   *
   * When it is enabled again, the items are re-added to the tracker.
   *
   */
  protected function disableEnableIndex() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // Disable the index
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('After disabling the index, no items should be tracked'));

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // Enable the index
    $edit = array(
      'status' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertNotNull($tracked_items, $this->t('After enabling the index, at least 1 item should be tracked'));
  }

  /**
   * Changes datasources from an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that, when an index changes the
   * datasource configurations, the tracker should remove all items from the
   * datasources it no longer needs to handle and add the new ones.
   */
  protected function changeIndexDatasource() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    $tracked_items = $this->countTrackedItems();
    $user_count = \Drupal::entityQuery('user')->count()->execute();
    $node_count = \Drupal::entityQuery('node')->count()->execute();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // enable indexing of users
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
      'datasources[]' => array('entity:user', 'entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $t_args = array(
      '!usercount' => $user_count,
      '!nodecount' => $node_count,
    );
    $this->assertEqual($tracked_items, $user_count+$node_count, $this->t('After enabling user and nodes with respectively !usercount users and !nodecount nodes we should have the sum of those to index', $t_args));

    $this->drupalGet($settings_path);
    // Disable indexing of users
    $edit = array(
      'datasources[]' => array('entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($tracked_items, $node_count, $this->t('After disabling user indexing we should only have !nodecount nodes to index', $t_args));
  }

  /**
   * Changes the server for an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that, when an index changes the
   * server configurations, the tracker should remove all items from the
   * server it no longer is attached to and add the new ones.
   */
  protected function changeIndexServer() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    $node_count = \Drupal::entityQuery('node')->count()->execute();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // enable indexing of nodes
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
      'datasources[]' => array('entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    // Reindex all so we start from scratch
    $index->reindex();
    // We should have as many nodes as the node count
    $remaining_items = $this->countRemainingItems();

    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($remaining_items, $node_count, $this->t('We should have !nodecount nodes to index', $t_args));
    // Index
    $index->index();

    $remaining_items = $this->countRemainingItems();

    $this->assertEqual($remaining_items, 0, $this->t('We should have nothing left to index', $t_args));

    // Create a second server
    $serverId2 = $this->createServer();

    // Go to the index edit path
    $this->drupalGet($settings_path);
    // Change servers in the UI
    $edit = array(
      'server' => $serverId2,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $remaining_items = $this->countRemainingItems();
    // After saving the new index, we should have called reindex.
    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($remaining_items, $node_count, $this->t('We should have !nodecount items left to index after changing servers', $t_args));
  }

  /**
   * Test that the filter can have fields configured.
   */
  protected function configureFilterFields() {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors[ignorecase][status]' => 1,
      'processors[ignorecase][settings][fields][search_api_language]' => FALSE,
      'processors[ignorecase][settings][fields][entity:node|title]' => 'entity:node|title',
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    if (isset($processors['ignorecase'])) {
      $configuration = $processors['ignorecase']->getConfiguration();
      $this->assertTrue(empty($configuration['fields']['search_api_language']), 'Language field disabled for ignore case filter.');
    }
    else {
      $this->fail('"Ignore case" processor not enabled.');
    }
  }

  /**
   * Returns the system path for an index.
   *
   * @param string $index_id
   *   The index ID.
   *
   * @return string
   *   A system path.
   */
  protected function getIndexPath($index_id) {
    return 'admin/config/search/search-api/index/' . $index_id;
  }

}
