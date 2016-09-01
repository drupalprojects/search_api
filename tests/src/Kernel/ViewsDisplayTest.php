<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests whether Views pages correctly create search display plugins.
 *
 * @group search_api
 */
class ViewsDisplayTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'field',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'search_api_test_example_content',
    'search_api_test_views',
    'search_api_test',
    'user',
    'system',
    'entity_test',
    'text',
    'views',
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (php_sapi_name() != 'cli') {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig(array(
      'search_api_test_example_content',
      'search_api_test_db',
      'search_api_test_views',
    ));
  }

  /**
   * Tests whether the search display plugin for the new view is available.
   */
  public function testViewsPageDisplayPluginAvailable() {
    $expected = array(
      'views_page:search_api_test_view__page_1',
    );
    $actual = $this->container
      ->get('plugin.manager.search_api.display')
      ->getDefinitions();
    $this->assertEquals($expected, array_keys($actual));
  }

}
