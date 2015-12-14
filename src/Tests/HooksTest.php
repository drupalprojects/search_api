<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\IntegrationTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;

/**
 * Tests integration of hooks.
 *
 * @group search_api
 */
class HooksTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('node', 'search_api', 'search_api_test_backend', 'search_api_test_views', 'search_api_test_hooks');

  /**
   * The id of the server.
   *
   * @var string
   */
  protected $serverId;

  /**
   * The id of the index.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Tests various operations via the Search API's admin UI.
   */
  public function testHooks() {
    $server_add_form = new Url('entity.search_api_server.add_form', array(), array('absolute' => TRUE));
    $index_add_form = new Url('entity.search_api_index.add_form', array(), array('absolute' => TRUE));

    // Create some nodes.
    $this->drupalCreateNode(array('type' => 'page'));
    $this->drupalCreateNode(array('type' => 'page'));
    $this->drupalCreateNode(array('type' => 'page'));

    // Log in, so we can test all the things.
    $this->drupalLogin($this->adminUser);

    // Create an index and server to work with.
    $this->createServer();
    $this->createIndex();

    // hook_search_api_backend_info_alter was triggered.
    $this->drupalGet($server_add_form);
    $this->assertText('Slims return');

    // hook_search_api_datasource_info_alter was triggered.
    $this->drupalGet($index_add_form);
    $this->assertText('Distant land');

    // hook_search_api_processor_info_alter was triggered.
    $this->drupalGet($this->getIndexPath('processors'));
    $this->assertText('Mystic bounce');

    $this->drupalGet($this->getIndexPath());
    $this->drupalPostForm(NULL, [], $this->t('Index now'));

    // hook_search_api_index_items_alter was triggered.
    $this->assertText('There are 2 items indexed on the server for this index.');
    $this->assertText('Successfully indexed 3 items.');
    $this->assertText('Stormy');

    // hook_search_api_items_indexed was triggered.
    $this->assertText('Please set me at ease');

    // hook_search_api_index_reindex was triggered.
    $this->drupalGet($this->getIndexPath('reindex'));
    $this->drupalPostForm(NULL, [], $this->t('Confirm'));
    $this->assertText('Montara');

    // hook_search_api_data_type_info_alter was triggered.
    $this->drupalGet($this->getIndexPath('fields'));
    $this->assertText('Peace/Dolphin dance');
    // The implementation of hook_search_api_field_type_mapping_alter has
    // removed all dates, so we can't see any timestamp anymore in the page.
    $this->assertNoText('timestamp');

    $this->drupalGet('search-api-test-fulltext');
    // hook_search_api_query_alter was triggered.
    $this->assertText('Funky blue note');
    // hook_search_api_results_alter was triggered.
    $this->assertText('Stepping into tomorrow');
    // hook_search_api_views_query_alter was triggered.
    $this->assertText('Andrew hill break');
  }

  /**
   * Creates a search server.
   */
  protected function createServer() {
    $this->serverId = Unicode::strtolower($this->randomMachineName());
    $server_add_form = new Url('entity.search_api_server.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($server_add_form);

    $edit = array(
      'name' => 'Search API test server',
      'id' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The server was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/server/' . $this->serverId, array(), 'Correct redirect to server page.');
  }

  /**
   * Creates an index.
   */
  protected function createIndex() {
    $index_add_form = new Url('entity.search_api_index.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($index_add_form);

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'id' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'server' => $this->serverId,
      'datasources[]' => array('entity:node'),
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The index was successfully saved.'));
    $this->assertUrl($this->getIndexPath(), array(), 'Correct redirect to index page.');
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
