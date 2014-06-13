<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Menu\LocalActionsTest.
 */

namespace Drupal\search_api\Tests\Menu;

use Drupal\Tests\UnitTestCase;

/**
 * Tests existence of Search API local actions.
 *
 * @group Drupal
 * @group search_api
 */
class LocalActionsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API local actions test',
      'description' => 'Test search API local actions.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests local task existence.
   */
  public function testLocalActions() {
    // @todo implement it once there is an integration test for local actions
  }

}
