<?php

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Tests\IntegrationTestBase;

/**
 * Tests the processors functionality of the Search API backend.
 *
 * @group search_api
 */
class ProcessorIntegrationTest extends IntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  public function testNodeStatusIntegration() {
    $this->addFilter('node_status');
  }

  public function testIgnoreCaseIntegration() {
    $this->addFilter('ignorecase');

    $edit = array(
      'processors[ignorecase][settings][fields][search_api_language]' => FALSE,
    );
    $this->editSettingsForm($edit, 'ignorecase');
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

  public function testTransliterationIntegration() {
    $this->addFilter('transliteration');

    $edit = array(
      'processors[transliteration][settings][fields][search_api_language]' => FALSE,
    );
    $this->editSettingsForm($edit, 'transliteration');
  }

  private function editSettingsForm($editArray, $processorName) {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $this->drupalPostForm($settings_path, $editArray, $this->t('Save'));

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    if (isset($processors[$processorName])) {
      $configuration = $processors[$processorName]->getConfiguration();
      $this->assertTrue(
        empty($configuration['fields']['search_api_language']),
        'Language field disabled for ' . $processorName . ' filter.'
      );
    }
    else {
      $this->fail($processorName . ' processor not enabled.');
    }

  }

}
