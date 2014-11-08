<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\ServerStorageUnitTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Server\ServerInterface;
use Drupal\simpletest\KernelTestBase;

/**
 * Tests whether the storage of search indexes works correctly.
 *
 * @group search_api
 */
class ServerStorageUnitTest extends KernelTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array('search_api', 'search_api_test_backend');

  /**
   * The search server storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface.
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_task'));
    $this->controller = $this->container->get('entity.manager')->getStorage('search_api_server');
  }

  /**
   * Tests all CRUD operations as a queue of operations.
   */
  public function testIndexCRUD() {
    $this->assertTrue($this->controller instanceof ConfigEntityStorage, 'The Search API Server storage controller is loaded.');

    $server = $this->serverCreate();
    $this->serverLoad($server);
    $this->serverDelete($server);
  }

  /**
   * Tests whether creating a server works correctly.
   *
   * @return \Drupal\search_api\Server\ServerInterface
   *   The newly created search server.
   */
  public function serverCreate() {
    $serverData = array(
      'machine_name' => $this->randomMachineName(),
      'name' => $this->randomString(),
      'backend' => 'search_api_test_backend',
    );

    $server = $this->controller->create($serverData);

    $this->assertTrue($server instanceof ServerInterface, 'The newly created entity is Search API Server.');

    $server->save();

    return $server;
  }

  /**
   * Tests whether loading a server works correctly.
   *
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   The server used for this test.
   */
  public function serverLoad(ServerInterface $server) {
    $loaded_server = $this->controller->load($server->id());
    $this->assertIdentical($server->get('label'), $loaded_server->get('label'));
  }

  /**
   * Tests whether deleting a server works correctly.
   *
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   The server used for this test.
   */
  public function serverDelete(ServerInterface $server) {
    $this->controller->delete(array($server));
    $loaded_server = $this->controller->load($server->id());
    $this->assertNull($loaded_server);
  }

}
