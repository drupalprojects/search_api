<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiServerTaskUnitTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Tests correct working of the server task system.
 */
class SearchApiServerTaskUnitTest extends EntityUnitTestBase {

  /**
   * The test server.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The content entity datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search_api', 'search_api_test_backend');

  /**
   * The server task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface
   */
  protected $serverTaskManager;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Search API Server Tasks',
      'description' => 'Tests the Search API server tasks system.',
      'group' => 'Search API',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    // Create a test server.
    $this->server = entity_create('search_api_server', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomName(),
      'status' => 1,
      'backendPluginId' => 'search_api_test_backend',
    ));
    $this->server->save();

    // Create a test index.
    $this->index = entity_create('search_api_index', array(
      'name' => $this->randomString(),
      'machine_name' => $this->randomName(),
      'status' => 1,
      'datasourcePluginIds' => array('entity:entity_test'),
      'trackerPluginId' => 'default_tracker',
      'serverMachineName' => $this->server->id(),
      'options' => array('index_directly' => FALSE),
    ));
    $this->index->save();

    $this->serverTaskManager = $this->container->get('search_api.server_task_manager');
  }

  /**
   * Tests task system integration for the server's addIndex() method.
   */
  public function testTaskCreation() {
    // Since we want to add the index, we should first remove it (even though it
    // shouldn't matter – just for logic consistency).
    $this->index->setServer(NULL);
    $this->index->save();

    // Set exception for addIndex() calls and reset the list of successful
    // backend method calls.
    $this->state->set('search_api_test_backend.exception.addIndex', TRUE);
    $this->getCalledServerMethods();

    // Try to add an index.
    $this->server->addIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'addIndex correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'addIndex';
    }
    $this->assertTrue(!empty($task_created), 'The addIndex task was successfully added.');
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), 'The right index ID was used for the addIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass('Second task (updateIndex) was added.');
      $this->assertEqual($tasks[0]->type, 'addIndex', 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, 'updateIndex', 'New task was queued as last.');
    }
    else {
      $this->fail('Second task (updateIndex) was not added.');
    }

    // Let addIndex() succeed again and check if a cron run properly executes
    // the tasks.
    $this->state->set('search_api_test_backend.exception.addIndex', FALSE);
    search_api_cron();
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed during cron run.');
    $this->assertEqual($this->getCalledServerMethods(), array('addIndex', 'updateIndex'), 'Right methods were called during task execution.');
  }

  /**
   * Retrieves the methods called on the test server.
   *
   * @param bool $reset
   *   (optional) Whether to reset the list after the called methods are
   *   retrieved.
   *
   * @return string[]
   *   The methods called on the test server since the last reset.
   */
  protected function getCalledServerMethods($reset = TRUE) {
    $key = 'search_api_test_backend.methods_called.' . $this->server->id();
    $methods_called = $this->state->get($key, array());
    if ($reset) {
      $this->state->delete($key);
    }
    return $methods_called;
  }

  /**
   * Get the tasks set on the test server.
   *
   * @return object[]
   *   All tasks read from the database for the test server, with numeric keys
   *   starting with 0.
   */
  protected function getServerTasks() {
    $tasks = array();
    $select = \Drupal::database()->select('search_api_task', 't');
    $select->fields('t')
      ->orderBy('id')
      ->condition('server_id', $this->server->id());
    foreach ($select->execute() as $task) {
      if ($task->data) {
        $task->data = unserialize($task->data);
      }
      $tasks[] = $task;
    }
    return $tasks;
  }

}
