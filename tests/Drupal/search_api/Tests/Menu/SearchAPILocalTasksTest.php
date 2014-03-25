<?php

/**
 * @file
 * Contains \Drupal\action\Tests\Menu\SearchAPILocalTasksTest.
 */

namespace Drupal\search_api\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of Search API local tasks.
 *
 * @group Search API
 */
class SearchAPILocalTasksTest extends LocalTaskIntegrationTest {

  public static function getInfo() {
    return array(
      'name' => 'Search API local tasks test',
      'description' => 'Test search API local tasks.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    // @TODO rename module directory name
    $this->directoryList = array('search_api' => 'modules/search_api__8_x_');
    parent::setUp();
  }

  /**
   * Tests local task existence.
   *
   */
  public function testSearchAPILocalTasks() {
    // @TODO this test is broken
//    $this->assertLocalTasks('search_api.server_view', array(array('search_api.server_view')));
//    $this->assertLocalTasks('search_api.server_edit', array(array('search_api.server_edit')));
//    $this->assertLocalTasks('search_api.index_view', array(array('search_api.index_view')));
//    $this->assertLocalTasks('search_api.index_edit', array(array('search_api.index_edit')));
//    $this->assertLocalTasks('search_api.index_fields', array(array('search_api.index_fields')));
//    $this->assertLocalTasks('search_api.filters', array(array('search_api.filters')));
  }

}
