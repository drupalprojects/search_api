<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Menu\LocalTasksTest.
 */

namespace Drupal\search_api\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of Search API local tasks.
 *
 * @group Drupal
 * @group search_api
 */
class LocalTasksTest extends LocalTaskIntegrationTest {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API local tasks test',
      'description' => 'Test search API local tasks.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->directoryList = array('search_api' => 'modules/search_api');
  }

  /**
   * Tests local task existence.
   *
   * @dataProvider getPageRoutesServer
   */
  public function testLocalTasksServer($route) {
    $tasks = array(
      0 => array('search_api.server_view', 'search_api.server_edit'),
    );
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Tests local task existence.
   *
   * @dataProvider getPageRoutesIndex
   */
  public function testLocalTasksIndex($route) {
    $tasks = array(
      0 => array('search_api.index_view', 'search_api.index_edit', 'search_api.index_fields', 'search_api.index_filters'),
    );
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provides a list of routes to test.
   */
  public function getPageRoutesServer() {
    return array(
      array('search_api.server_view'),
      array('search_api.server_edit'),
    );
  }

  /**
   * Provides a list of routes to test.
   */
  public function getPageRoutesIndex() {
    return array(
      array('search_api.index_view'),
      array('search_api.index_edit'),
      array('search_api.index_filters'),
    );
  }

}
