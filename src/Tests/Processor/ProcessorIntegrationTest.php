<?php

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Tests\IntegrationTestBase;

class ProcessorIntegrationTest extends IntegrationTestBase {

  public function testNodeStatusIntegration() {
    $this->addFilter('node_status');
  }

  public function testIgnoreCaseIntegration() {
    $this->addFilter('ignorecase');

    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors[ignorecase][settings][fields][search_api_language]' => FALSE,
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

  public function testUrlFieldIntegration() {
    $this->addFilter('add_url');
  }

  public function testContentAccessIntegration() {
    $this->addFilter('content_access');
  }

  public function testLanguageIntegration() {
    $this->addFilter('language');
  }

}
