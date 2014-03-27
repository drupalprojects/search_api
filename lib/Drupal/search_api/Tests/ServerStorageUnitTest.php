<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\ServerStorageUnitTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\simpletest\DrupalUnitTestBase;
use Drupal\search_api\Entity\Server;

/**
 * Class ServerStorageUnitTest
 *
 * Tests Search API Server entity storage
 *
 * @package Drupal\search_api\Test
 */
class ServerStorageUnitTest extends DrupalUnitTestBase {

  /**
   * Modules to enabled.
   *
   * @var array
   */
  public static $modules = array('search_api');

  /**
   * Search API Server storage controller.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface.
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Server storage unit tests',
      'description' => 'Test CRUD operation of Search API Server entity',
      'group' => 'Search API'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->controller = $this->container->get('entity.manager')->getStorage('search_api_server');
  }

  /**
   * Test all CRUD operations here as a queue of operations.
   */
  public function testIndexCRUD() {
    $this->assertTrue($this->controller instanceof ConfigEntityStorage, 'The Search API Server storage controller is loaded.');

    $index = $this->serverCreate();

    $this->serverLoad($index);
    $this->serverDelete($index);
  }

  /**
   * Tests Server creation.
   *
   * @return Server newly created instance of Index.
   */
  public function serverCreate() {
    $serverData = array(
      'machine_name' => $this->randomName(),
      'name' => $this->randomString(),
    );

    try {
      $entity = $this->controller->create($serverData);
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage() . ' exception was thrown.');
    }

    $this->assertTrue($entity instanceof Server, 'The newly created entity is Search API Server.');

    $entity->save();

    return $entity;
  }

  /**
   * Test Index loading.
   *
   * @param $index Server
   */
  public function serverLoad($server) {
    try {
      $entity = $this->controller->load($server->id());
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage() . ' exception was thrown.');
    }

    $this->assertIdentical($server->get('label'), $entity->get('label'));
  }

  /**
   * Test of deletion of given index.
   *
   * @param $server
   */
  public function serverDelete($server) {
    try {
      $this->controller->delete(array($server));
    }
    catch (\Exception $e) {
      $this->fail($e->getMessage() . ' exception was thrown.');
    }

    $entity = $this->controller->load($server->id());

    $this->assertFalse($entity);
  }
}