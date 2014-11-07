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

  public function testTokenizerIntegration() {
    $this->addFilter('tokenizer');

    $edit = array(
      'processors[tokenizer][settings][spaces]' => '',
      'processors[tokenizer][settings][overlap_cjk]' => FALSE,
      'processors[tokenizer][settings][minimum_word_size]' => 2,
    );
    $this->editSettingsForm($edit, 'tokenizer');
  }

  public function testStopWordsIntegration() {
    $this->addFilter('stopwords');

    $edit = array(
      'processors[stopwords][settings][stopwords]' => 'the',
    );
    $this->editSettingsForm($edit, 'stopwords');
  }

  public function testRenderedItemIntegration() {
    $this->addFilter('rendered_item');

    $edit = array(
      'processors[rendered_item][settings][roles][]' => 'authenticated',
      'processors[rendered_item][settings][view_mode][entity:node]' => 'default',
    );
    $this->editSettingsForm($edit, 'rendered_item');
  }

  public function testIgnoreCharactersIntegration() {
    $this->addFilter('ignore_character');

    $edit = array(
      'processors[ignore_character][settings][fields][search_api_language]' => FALSE,
      'processors[ignore_character][settings][ignorable]' => '[¿¡!?,.]',
      'processors[ignore_character][settings][strip][character_sets][Cc]' => TRUE,
    );
    $this->editSettingsForm($edit, 'ignore_character');
  }

  public function testHTMLFilterIntegration() {
    $this->addFilter('html_filter');

    $edit = array(
      'processors[html_filter][settings][fields][search_api_language]' => FALSE,
      'processors[html_filter][settings][title]' => FALSE,
      'processors[html_filter][settings][alt]' => FALSE,
      'processors[html_filter][settings][tags]' => 'h1: 10'
    );
    $this->editSettingsForm($edit, 'html_filter');
  }

  public function testHighlightFilter() {
    $this->addFilter('highlight');

    $edit = array(
      'processors[highlight][settings][highlight]' => 'never',
      'processors[highlight][settings][excerpt]' => FALSE,
      'processors[highlight][settings][excerpt_length]' => 128,
      'processors[highlight][settings][prefix]' => '<em>',
      'processors[highlight][settings][suffix]' => '</em>',
    );
    $this->editSettingsForm($edit, 'highlight');
  }

  /**
   * Post edit settings to admin form
   *
   * @param array $editArray
   * @param string $processorName
   */
  private function editSettingsForm($editArray, $processorName) {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $this->drupalPostForm($settings_path, $editArray, $this->t('Save'));

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    if (isset($processors[$processorName])) {
      $configuration = $processors[$processorName]->getConfiguration();
      $this->assertTrue(
        empty($configuration['fields']['search_api_language'])
      );
    }
    else {
      $this->fail($processorName . ' settings not applied.');
    }

  }

}
