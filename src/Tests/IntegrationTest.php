<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\IntegrationTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Exception\SearchApiException;

/**
 * Tests the overall functionality of the Search API framework and admin UI.
 *
 * @group search_api_debug
 */
class IntegrationTest extends SearchApiWebTestBase {

  /**
   * The ID of the search server used for this test.
   *
   * @var string
   */
  protected $serverId;

  /**
   * The ID of the search index used for this test.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Tests various operations via the Search API's admin UI.
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
    $this->checkContentEntityTracking();

    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
    $this->removeFieldsFromIndex();

    $this->addFilter();
    $this->configureFilter();

    $this->setReadOnly();
    $this->disableEnableIndex();
    $this->changeIndexDatasource();
    $this->changeIndexServer();

  }

  /**
   * Tests creating a search server via the UI.
   */
  protected function createServer() {
    $this->serverId = Unicode::strtolower($this->randomMachineName());
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_server.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200, 'Server add page exists');

    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Server name'))));

    $edit = array(
      'name' => 'Search API test server',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The server was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/server/' . $this->serverId, array(), 'Correct redirect to server page.');
  }

  /**
   * Tests creating a search index via the UI.
   */
  protected function createIndex() {
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_index.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'status' => 1,
      'description' => 'An index used for testing.',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Index name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Data types'))));

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'server' => $this->serverId,
      'datasources[]' => array('entity:node'),
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The index was successfully saved.'));
    $this->assertUrl($this->getIndexPath(), array(), 'Correct redirect to index page.');

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    if ($this->assertTrue($index, 'Index was correctly created.')) {
      $this->assertEqual($index->label(), $edit['name'], 'Name correctly inserted.');
      $this->assertEqual($index->id(), $edit['machine_name'], 'Index machine name correctly inserted.');
      $this->assertTrue($index->status(), 'Index status correctly inserted.');
      $this->assertEqual($index->getDescription(), $edit['description'], 'Index machine name correctly inserted.');
      $this->assertEqual($index->getServerId(), $edit['server'], 'Index server machine name correctly inserted.');
      $this->assertEqual($index->getDatasourceIds(), $edit['datasources[]'], 'Index datasource id correctly inserted.');
    }
    else {
      // Since none of the other tests would work, bail at this point.
      throw new SearchApiException();
    }
  }

  /**
   * Tests whether the tracking information is properly maintained.
   *
   * Will especially test the bundle option of the content entity datasource.
   */
  protected function checkContentEntityTracking() {
    // Initially there should be no tracked items, because there are no nodes.
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, 'No items are tracked yet.');

    // Add two articles and a page.
    $article1 = $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the tracking table immediately.
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, 'Three items are tracked.');

    // Test disabling the index.
    $settings_path = $this->getIndexPath('edit');
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, 'No items are tracked.');

    // Test re-enabling the index.
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, 'Three items are tracked.');

    // Uncheck "default" and don't select any bundles. This should remove all
    // items from the tracking table.
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, 'No items are tracked.');

    // Leave "default" unchecked and select the "article" bundle. This should
    // re-add the two articles to the tracking table.
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, 'Two items are tracked.');

    // Leave "default" unchecked and select only the "page" bundle. This should
    // result in only the page being present in the tracking table.
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, 'One item is tracked.');

    // Check "default" again and select the "article" bundle. This shouldn't
    // change the tracking table, which should still only contain the page.
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, 'One item is tracked.');

    // Leave "default" checked but now select only the "page" bundle. This
    // should result in only the articles being tracked.
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, 'Two items are tracked.');

    // Delete an article. That should remove it from the item table.
    $article1->delete();

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, 'One item is tracked.');
  }

  /**
   * Counts the number of tracked items in the test index.
   *
   * @return int
   *   The number of tracked items in the test index.
   */
  protected function countTrackedItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getTotalItemsCount();
  }

  /**
   * Counts the number of unindexed items in the test index.
   *
   * @return int
   *   The number of unindexed items in the test index.
   */
  protected function countRemainingItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getRemainingItemsCount();
  }

  /**
   * Tests whether adding fields to the index works correctly.
   */
  protected function addFieldsToIndex() {
    $edit = array(
      'fields[entity:node|nid][indexed]' => 1,
      'fields[entity:node|title][indexed]' => 1,
      'fields[entity:node|title][type]' => 'text',
      'fields[entity:node|title][boost]' => '21.0',
      'fields[entity:node|body][indexed]' => 1,
    );

    $this->drupalPostForm($this->getIndexPath('fields'), $edit, $this->t('Save changes'));
    $this->assertText($this->t('The changes were successfully saved.'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields(FALSE);

    $this->assertEqual($fields['entity:node|nid']->isIndexed(), $edit['fields[entity:node|nid][indexed]'], 'nid field is indexed.');
    $this->assertEqual($fields['entity:node|title']->isIndexed(), $edit['fields[entity:node|title][indexed]'], 'title field is indexed.');
    $this->assertEqual($fields['entity:node|title']->getType(), $edit['fields[entity:node|title][type]'], 'title field type is text.');
    $this->assertEqual($fields['entity:node|title']->getBoost(), $edit['fields[entity:node|title][boost]'], 'title field boost value is 21.');

    // Check that a 'parent_data_type.data_type' Search API field type => data
    // type mapping relationship works.
    $this->assertEqual($fields['entity:node|body']->getType(), 'text', 'Complex field mapping relationship works.');
  }

  /**
   * Tests the "Add related fields" functionality on the index's "Fields" form.
   */
  protected function addAdditionalFieldsToIndex() {
    // Test that an entity reference field which targets a content entity is
    // shown.
    $this->assertFieldByName('additional[field][entity:node|uid]', NULL, 'Additional entity reference field targeting a content entity type is displayed.');

    // Test that an entity reference field which targets a config entity is not
    // shown as an additional field option.
    $this->assertNoFieldByName('additional[field][entity:node|type]', NULL,'Additional entity reference field targeting a config entity type is not displayed.');

    // @todo Implement more tests for additional fields.
  }

  /**
   * Tests whether removing fields from the index works correctly.
   */
  protected function removeFieldsFromIndex() {
    $edit = array(
      'fields[entity:node|body][indexed]' => FALSE,
    );
    $this->drupalPostForm($this->getIndexPath('fields'), $edit, $this->t('Save changes'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();
    $this->assertTrue(!isset($fields['entity:node|body']), 'The body field has been removed from the index.');
  }

  /**
   * Tests that enabling a processor works.
   */
  protected function addFilter() {
    $edit = array(
      'processors[ignorecase][status]' => 1,
    );
    $this->drupalPostForm($this->getIndexPath('filters'), $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    $this->assertTrue(isset($processors['ignorecase']), 'Ignore case processor enabled');
  }

  /**
   * Tests that configuring a processor works.
   */
  protected function configureFilter() {
    $edit = array(
      'processors[ignorecase][status]' => 1,
      'processors[ignorecase][settings][fields][search_api_language]' => FALSE,
      'processors[ignorecase][settings][fields][entity:node|title]' => 'entity:node|title',
    );
    $this->drupalPostForm($this->getIndexPath('filters'), $edit, $this->t('Save'));
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
   * Sets an index to "read only" and checks if it reacts correctly.
   *
   * The expected behavior is that, when an index is set to "read only", it
   * keeps tracking but won't index any items.
   */
  protected function setReadOnly() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    $index_path = $this->getIndexPath();
    $settings_path = $index_path . '/edit';

    // Re-enable tracking of all bundles. After this there should be two
    // unindexed items tracked by the index.
    $edit = array(
      'status' => TRUE,
      'read_only' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $remaining_before = $this->countRemainingItems();

    $this->drupalGet($index_path);

    $this->assertNoText($this->t('Index now'), 'The "Index now" button is not displayed.');

    // Also try indexing via the API to make sure it is really not possible.
    $indexed = $index->index();
    $this->assertEqual(0, $indexed, 'No items were indexed after setting the index to "read only".');
    $remaining_after = $this->countRemainingItems();
    $this->assertEqual($remaining_before, $remaining_after, 'No items were indexed after setting the index to "read only".');

    // Disable "read only" and verify indexing now works again.
    $edit = array(
      'read_only' => FALSE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $this->drupalPostForm($index_path, array(), $this->t('Index now'));

    $remaining_after = $index->getTracker()->getRemainingItemsCount();
    $this->assertEqual(0, $remaining_after, 'Items were indexed after removing the "read only" flag.');

  }

  /**
   * Disables and enables an index and checks if it reacts correctly.
   *
   * The expected behavior is that, when an index is disabled, all its items
   * are removed from both the tracker and the server.
   *
   * When it is enabled again, the items are re-added to the tracker.
   */
  protected function disableEnableIndex() {
    // Disable the index and check that no items are tracked.
    $settings_path = $this->getIndexPath('edit');
    $edit = array(
      'status' => FALSE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual(0, $tracked_items, 'No items are tracked after disabling the index.');
    $tracked_items = \Drupal::database()->select('search_api_item', 'i')->countQuery()->execute()->fetchField();
    $this->assertEqual(0, $tracked_items, 'No items left in tracking table.');

    // @todo Also try to verify whether the items got deleted from the server.

    // Re-enable the index and check that the items are tracked again.
    $edit = array(
      'status' => TRUE,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual(2, $tracked_items, 'After enabling the index, 2 items are tracked.');
  }

  /**
   * Changes the index's datasources and checks if it reacts correctly.
   *
   * The expected behavior is that, when an index's datasources are changed, the
   * tracker should remove all items from the datasources it no longer needs to
   * handle and add the new ones.
   */
  protected function changeIndexDatasource() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    $user_count = \Drupal::entityQuery('user')->count()->execute();
    $node_count = \Drupal::entityQuery('node')->count()->execute();

    // Enable indexing of users.
    $settings_path = $this->getIndexPath('edit');
    $edit = array(
      'datasources[]' => array('entity:user', 'entity:node'),
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, $user_count + $node_count, 'Correct number of items tracked after enabling the "User" datasource.');

    // Disable indexing of users again.
    $edit = array(
      'datasources[]' => array('entity:node'),
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, $node_count, 'Correct number of items tracked after disabling the "User" datasource.');
  }

  /**
   * Changes the index's server and checks if it reacts correctly.
   *
   * The expected behavior is that, when an index's server is changed, all of
   * the index's items should be removed from the previous server and marked as
   * "unindexed" in the tracker.
   */
  protected function changeIndexServer() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    $node_count = \Drupal::entityQuery('node')->count()->execute();
    $this->assertEqual($node_count, $this->countTrackedItems(), 'All nodes are correctly tracked by the index.');

    // Index all remaining items on the index.
    $index->index();

    $remaining_items = $this->countRemainingItems();
    $this->assertEqual($remaining_items, 0, 'All items have been successfully indexed.');

    // Create a second search server.
    $this->createServer();

    // Change the index's server to the new one.
    $settings_path = $this->getIndexPath('edit');
    $edit = array(
      'server' => $this->serverId,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));

    // After saving the new index, we should have called reindex.
    $remaining_items = $this->countRemainingItems();
    $this->assertEqual($remaining_items, $node_count, 'All items still need to be indexed.');
  }

  /**
   * Returns the system path for the test index.
   *
   * @param string|null $tab
   *   (optional) If set, the path suffix for a specific index tab.
   *
   * @return string
   *   A system path.
   */
  protected function getIndexPath($tab = NULL) {
    $path = 'admin/config/search/search-api/index/' . $this->indexId;
    if ($tab) {
      $path .= "/$tab";
    }
    return $path;
  }

}
