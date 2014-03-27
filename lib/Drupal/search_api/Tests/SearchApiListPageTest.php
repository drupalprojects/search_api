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
  protected $userWithLimitedPermissions;
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
    $this->assertRaw($server->get('description'), 'Description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '")]//span[@class="search-api-entity-status-enabled"]', NULL, 'Server is in proper table');

  }

  public function _testNewIndexCreate() {
    $server = $this->createServer();
    $index = $this->createIndex($server);

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($index->label(), 'Server presents on overview page.');
    $this->assertRaw($index->get('description'), 'Description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '")]//span[@class="search-api-entity-status-enabled"]', NULL, 'Index is in proper table');

  }

  public function _testEntityStatusChange() {
    $server = $this->createServer();

    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '")]//span[@class="search-api-entity-status-enabled"]', NULL, 'Server is in proper table');

    $this->drupalGet($this->urlGenerator->generateFromRoute('search_api.server_enable', array('search_api_server' => $server->id())));
    $this->assertUrl($this->urlGenerator->generateFromRoute('search_api.server_edit', array('search_api_server' => $server->id())), array(), 'Enable link with bypass token');
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '")]//span[@class="search-api-entity-status-disabled"]', NULL, 'Server is in proper table');
  }

  public function _testOperations() {
    $server = $this->createServer();

    $this->drupalGet($this->overviewPageUrl);
    $basicUrl = $this->urlGenerator->generateFromRoute('search_api.server_view', array('search_api_server' => $server->id()));
    $this->assertRaw('<a href="' . $basicUrl .'">canonical</a>', 'Canoninal operation presents');
    $this->assertRaw('<a href="' . $basicUrl .'/edit">edit-form</a>', 'Edit operation presents');
    $this->assertRaw('<a href="' . $basicUrl .'/disable">disable</a>', 'Disable operation presents');
    $this->assertRaw('<a href="' . $basicUrl .'/delete">delete-form</a>', 'Delete operation presents');
    $this->assertNoRaw('<a href="' . $basicUrl .'/enable">enabled-form</a>', 'Enable operation doesn\'t present');

    $server->setStatus(FALSE)->save();
    $this->drupalGet($this->overviewPageUrl);

    $this->assertRaw('<a href="' . $basicUrl .'/enable">enable</a>', 'Enable operation present');
    $this->assertNoRaw('<a href="' . $basicUrl .'/disable">disable-form</a>', 'Disable operation  doesn\'t presents');
  }

  public function _testAccess() {
    $this->drupalLogin($this->unauthorizedUser);

    $this->drupalGet($this->overviewPageUrl);

    $this->assertResponse(403, "User without permissions does have access to this page.");
  }
} 