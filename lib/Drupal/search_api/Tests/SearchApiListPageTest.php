<?php
/**
 * Created by PhpStorm.
 * User: artyommiroshnik
 * Date: 3/26/14
 * Time: 1:03 PM
 */

namespace Drupal\search_api\Tests;


class SearchApiListPageTest extends SearchApiWebTest {

  protected $unauthorizedUser;
  protected $overviewPageUrl;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API overview page tests',
      'description' => 'Test Search API overview page and what would be modified according to different server/index modifications.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp();

    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->overviewPageUrl = 'admin/config/search/search-api';
    $this->drupalLogin($this->testUser);
  }

  public function testNewServerCreate() {
    $server = $this->createServer();

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($server->label(), 'Server presents on overview page.');
    $this->assertText($server->get('description'), 'Description is present');
    $this->assertFieldByXPath('//table[class="enabled-servers-list"]//span[class="search-api-entity-status-enabled"]', NULL, 'Server is in proper table');

  }

  public function testNewIndexCreate() {
    $server = $this->createServer();
    $index = $this->createIndex($server);

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($index->label(), 'Server presents on overview page.');
    $this->assertText($index->get('description'), 'Description is present');
    $this->assertFieldByXPath('//table[class="enabled-servers-list"]//span[class="search-api-entity-status-enabled"]', NULL, 'Index is in proper table');

  }

  public function testEntityStatusChange() {

  }

  public function testOperations() {

  }

  public function testAccess() {
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet($this->overviewPageUrl);
  }
} 