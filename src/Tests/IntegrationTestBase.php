<?php
/**
 * Created by PhpStorm.
 * User: joris.vercammen
 * Date: 07/11/14
 * Time: 10:53
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Exception\SearchApiException;

class IntegrationTestBase extends SearchApiWebTestBase {

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Creates a search api server and index, and tracks content
   */
  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $this->serverId = $this->createServer();
    $this->createIndex();
    $this->trackContent();
  }

  protected function createServer() {
    $server_id = Unicode::strtolower($this->randomMachineName());
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
      'machine_name' => $server_id,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The server was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/server/' . $server_id, array(), $this->t('Correct redirect to server page.'));
    return $server_id;
  }

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
    $this->assertUrl('admin/config/search/search-api/index/' . $this->indexId, array(), $this->t('Correct redirect to index page.'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    if ($this->assertTrue($index, 'Index was correctly created.')) {
      $this->assertEqual($index->label(), $edit['name'], $this->t('Name correctly inserted.'));
      $this->assertEqual($index->id(), $edit['machine_name'], $this->t('Index machine name correctly inserted.'));
      $this->assertTrue($index->status(), $this->t('Index status correctly inserted.'));
      $this->assertEqual($index->getDescription(), $edit['description'], $this->t('Index machine name correctly inserted.'));
      $this->assertEqual($index->getServerId(), $edit['server'], $this->t('Index server machine name correctly inserted.'));
      $this->assertEqual($index->getDatasourceIds(), $edit['datasources[]'], $this->t('Index datasource id correctly inserted.'));
    }
    else {
      throw new SearchApiException();
    }
  }
  protected function trackContent() {
    // Initially there should be no tracked items, because there are no nodes
    $tracked_items = $this->countTrackedItems();

    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked yet'));

    // Add two articles and a page
    $article1 = $this->drupalCreateNode(array('type' => 'article'));
    $article2 = $this->drupalCreateNode(array('type' => 'article'));
    $page1 = $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the index immediately
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, $this->t('Three items are tracked'));

    // Create the edit index path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';

    // Test disabling the index
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked'));

    // Test enabling the index
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, $this->t('Three items are tracked'));

    // Test putting default to zero and no bundles checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all the items should get deleted.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked'));

    // Test putting default to zero and the article bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->drupalGet($settings_path);

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, $this->t('Two items are tracked'));

    // Test putting default to zero and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));

    // Test putting default to one and the article bundle checked.
    // This will add all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));

    // Test putting default to one and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, $this->t('Two items are tracked'));

    // Now lets delete an article. That should remove one item from the item
    // table
    $article1->delete();

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));
  }


  /**
   * Counts the number of tracked items from an index.
   *
   * @return int
   */
  protected function countTrackedItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getTotalItemsCount();
  }

  /**
   * Counts the number of remaining items from an index.
   *
   * @return int
   */
  protected function countRemainingItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getRemainingItemsCount();
  }

  /**
   * Test that a filter can be added.
   */
  protected function addFilter($filterName) {
    // Go to the index filter path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors['.$filterName.'][status]' => 1,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    $this->assertTrue(isset($processors[$filterName]), ucfirst($filterName) . ' processor enabled');
  }
}
