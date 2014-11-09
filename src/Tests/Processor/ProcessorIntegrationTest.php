<?php

namespace Drupal\search_api\Tests\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Tests\SearchApiWebTestBase;

/**
 * Tests the processors functionality of the Search API backend.
 *
 * @group search_api
 */
class ProcessorIntegrationTest extends SearchApiWebTestBase {

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);

    $this->createServer();
    $this->createIndex();
    $this->trackContent();
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

  /**
   * Test that a filter can be added.
   */
  private function addFilter($filterName) {
    // Go to the index filter path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors['.$filterName.'][status]' => 1,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    $this->assertTrue(isset($processors[$filterName]), ucfirst($filterName) . ' processor enabled');
  }

  /**
   * Creates a server, so we can test the processors trough the UI
   */
  protected function createServer() {
    $this->serverId = Unicode::strtolower($this->randomMachineName());
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_server.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200, 'Server add page exists');

    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Server name'))));

    $edit = array(
      'name' => 'Search API test server',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The server was successfully saved.'));
  }

  /**
   * Creates an index, so we can test the processors trough the UI
   */
  protected function createIndex() {
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_index.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'status' => 1,
      'description' => 'An index used for testing.',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Index name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Data types'))));

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'server' => $this->serverId,
      'datasources[]' => array('entity:node'),
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The index was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/index/' . $this->indexId, array(), $this->t('Correct redirect to index page.'));
  }

  /**
   * Adds content to index.
   */
  private function trackContent() {
    // Initially there should be no tracked items, because there are no nodes
    $tracked_items = $this->countTrackedItems();

    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked yet'));

    // Add two articles and a page
    $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalCreateNode(array('type' => 'article'));
    $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the index immediately
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, $this->t('Three items are tracked'));
  }

  /**
   * Counts the number of tracked items from an index.
   *
   * @return int
   */
  private function countTrackedItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getTotalItemsCount();
  }

  /**
   * Counts the number of remaining items from an index.
   *
   * @return int
   */
  private function countRemainingItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getRemainingItemsCount();
  }

}
