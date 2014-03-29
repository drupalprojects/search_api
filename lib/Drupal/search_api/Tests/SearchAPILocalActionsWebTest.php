<?php

/**
 * @file
 * Contains \Drupal\action\Tests\Menu\SearchAPILocalActionsTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\system\Tests\Menu\LocalActionTest;

class SearchAPILocalActionsWebTest extends LocalActionTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('search_api');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API local actions web test',
      'description' => 'Test search API local actions.',
      'group' => 'Search API',
    );
  }

  protected $adminUser;

  public function setUp() {
    parent::setUp();
    // Create users.
    $this->adminUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->drupalLogin($this->adminUser);
  }

  public function testLocalAction() {}

  /**
   * Tests local actions existence.
   *
   * no data provider here :( keeping this structure for later ActionIntegration unit test.
   */
  public function testLocalActions() {
    foreach ($this->getSearchAPIPageRoutes() as $routes) {
      foreach ($routes as $route) {
        $actions = array(
          '/admin/config/search/search-api/add-server' => 'Add server', // search_api.server_add
          '/admin/config/search/search-api/add-index' => 'Add index', // search_api.index_add
        );
        $this->drupalGet($route);
        $this->assertLocalAction($actions);
      }
    }
  }

  /**
   * Provides a list of routes to test.
   */
  public function getSearchAPIPageRoutes() {
    return array(
      array('/admin/config/search/search-api'), // search_api.overview
    );
  }
}