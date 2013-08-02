<?php
/**
 * @file
 * Contains Drupal\search_api\Tests\UpgradePathTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Language\Language;
use Drupal\system\Tests\Upgrade\UpgradePathTestBase;

/**
 * Tests upgrading a Drupal 7 install with Search API and some data to Drupal 8.
 */
class UpgradePathTest extends UpgradePathTestBase {

  /**
   * Provides information about this test.
   *
   * @return array
   *   An array containing the following keys:
   *   - name: The test suite's human-readable name.
   *   - description: A short description about these tests.
   *   - group: A group under which this test suite will be listed.
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API upgrade test',
      'description' => 'Upgrade tests with Search API data.',
      'group' => 'Upgrade path',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Path to the database dump files.
    $this->databaseDumpFiles = array(
      drupal_get_path('module', 'system') . '/tests/upgrade/drupal-7.bare.minimal.database.php.gz',
      drupal_get_path('module', 'search_api') . '/tests/upgrade/drupal-7.database.php',
    );
    parent::setUp();
  }

  /**
   * Tests a D7 -> D8 upgrade.
   */
  public function testUpgrade() {
    $this->assertTrue($this->performUpgrade(), 'The upgrade was completed successfully.');

    $config = \Drupal::config('search_api.settings');
    $this->assertTrue($config, 'Search API configuration was found.');
    if ($config) {
      $this->assertEqual($config->get('cron_batch_count'), 20, 'Cron batch count was correctly upgraded.');
      $this->assertEqual($config->get('cron_worker_runtime'), 30, 'Cron worker runtime was correctly upgraded.');
    }

    $tasks = \Drupal::state()->get('search_api_tasks');
    $this->assertTrue($tasks, 'Search API tasks were found.');
    if ($tasks) {
      $expected['test']['test'][] = 'remove';
      $this->assertEqual($tasks, $expected, 'Tasks were correctly upgraded.');
    }

    $index = search_api_index_load('test_index');
    $this->assertTrue($index, 'Search API index was found.');
    if ($tasks) {
      $this->assertTrue(!isset($index->id), 'Index "id" field was removed.');
      $this->assertTrue(!empty($index->uuid), 'Index "uuid" field was added.');
      $this->assertEqual($index->langcode, Language::LANGCODE_NOT_SPECIFIED, 'Index "langcode" field was set correctly.');
    }
  }

}
