<?php
/**
 * @file
 * Definition of \Drupal\search_api\Tests\SearchApiListPageTest.
 */

namespace Drupal\search_api\Tests;


class SearchApiListPageTest extends SearchApiWebTestBase {

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

    $this->drupalLogin($this->adminUser);

    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->overviewPageUrl = 'admin/config/search/search-api';

  }

  public function testNewServerCreate() {
    /** @var $index \Drupal\search_api\Entity\Server */
    $server = $this->getTestServer();

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($server->label(), 'Server present on overview page.');
    $this->assertRaw($server->get('description'), 'Description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '")]//span[@class="search-api-entity-status-enabled"]', NULL, 'Server is in proper table');
  }

  public function testNewIndexCreate() {
    $this->getTestServer();
    $index = $this->getTestIndex();

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($index->label(), 'Index present on overview page.');
    $this->assertRaw($index->get('description'), 'Index description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '")]//span[@class="search-api-entity-status-enabled"]', NULL, 'Index is in proper table');
  }

  public function testEntityStatusChange() {
    /** @var $index \Drupal\search_api\Entity\Server */
    $server = $this->getTestServer();
    $index = $this->getTestIndex();

    $server->set('status', FALSE)->save();

    $link = $this->urlGenerator->generateFromRoute('search_api.server_enable', array('search_api_server' => $server->id()));
    $this->drupalGet($link);
    //$this->assertUrl($this->urlGenerator->generateFromRoute('search_api.server_enable', array('search_api_server' => $server->id())), array(), 'Enable link with bypass token');
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '")]//span[@class="search-api-entity-status-disabled"]', NULL, 'Server is in proper table');


  }

  public function testOperations() {
    /** @var $index \Drupal\search_api\Entity\Server */
    $server = $this->getTestServer();

    $this->drupalGet($this->overviewPageUrl);
    $basic_url = $this->urlGenerator->generateFromRoute('search_api.server_view', array('search_api_server' => $server->id()));
    $this->assertRaw('<a href="' . $basic_url . '">canonical</a>', 'Canonical operation presents');
    $this->assertRaw('<a href="' . $basic_url . '/edit">edit-form</a>', 'Edit operation presents');
    $this->assertRaw('<a href="' . $basic_url . '/disable">disable</a>', 'Disable operation presents');
    $this->assertRaw('<a href="' . $basic_url . '/delete">delete-form</a>', 'Delete operation presents');
    $this->assertNoRaw('<a href="' . $basic_url . '/enable">enabled-form</a>', 'Enable operation is not present');

    $server->setStatus(FALSE)->save();
    $this->drupalGet($this->overviewPageUrl);

    $this->assertRaw('<a href="' . $basic_url .'/enable">enable</a>', 'Enable operation present');
    $this->assertNoRaw('<a href="' . $basic_url .'/disable">disable-form</a>', 'Disable operation  is not present');
  }

  public function testAccess() {
    $this->drupalGet($this->overviewPageUrl);
    $this->assertResponse(200, 'Admin user can access the overview page.');

    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet($this->overviewPageUrl);
    $this->assertResponse(403, "User without permissions does have access to this page.");
  }
}
