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
  public function testAddIndex() {
    // Since we want to add the index, we should first remove it (even though it
    // shouldn't matter – just for logic consistency).
    $this->index->setServer(NULL);
    $this->index->save();

    $this->checkTaskSystem('addIndex');
  }

  /**
   * Tests task system integration for the server's updateIndex() method.
   */
  public function testUpdateIndex() {
    // Since we want to add the index, we should first remove it (even though it
    // shouldn't matter – just for logic consistency).
    $this->index->setServer(NULL);
    $this->index->save();

    $this->checkTaskSystem('updateIndex', 'addIndex', 'updateIndex');
  }

  /**
   * Checks server task system integration for a single method.
   *
   * Used as a helper to avoid code duplication when checking all the methods.
   *
   * @param string $type
   *   One of the methods that has server task system integration – "addIndex",
   *   "updateIndex", "removeIndex", "deleteItems" or "deleteAllIndexItems".
   * @param string $second_type
   *   (optional) A second type to use for the second call.
   * @param string|null $third_type
   *   (optional) A third type to use for final triggering of task execution. If
   *   not given, use a cron run instead.
   */
  public function checkTaskSystem($type, $second_type = 'updateIndex', $third_type = NULL) {
    // Set exception for the tested method and reset the list of successful
    // backend method calls.
    $this->state->set("search_api_test_backend.exception.$type", TRUE);
    $this->getCalledServerMethods();

    // Try to add an index.
    $this->server->$type($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), "$type correctly threw an exception.");
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === $type;
    }
    $this->assertTrue(!empty($task_created), "The $type task was successfully added.");
    if ($tasks) {
      $this->assertEqual($tasks[0]->index_id, $this->index->id(), "The right index ID was used for the $type task.");
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->$second_type($this->index);
    $this->assertEqual($this->getCalledServerMethods(), array(), "$second_type was not executed.");
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->pass("Second task ($second_type) was added.");
      $this->assertEqual($tasks[0]->type, $type, 'First task stayed the same.');
      $this->assertEqual($tasks[1]->type, $second_type, 'New task was queued as last.');
    }
    else {
      $this->fail("Second task ($second_type) was not added.");
    }

    // Let $type() succeed again, then trigger the task execution either with
    // a third method call or a cron run.
    $this->state->set("search_api_test_backend.exception.$type", FALSE);
    $expected_methods = array($type, $second_type);
    if ($third_type) {
      $expected_methods[] = $third_type;
      $this->server->$third_type($this->index);
    }
    else {
      search_api_cron();
    }
    $this->assertEqual($this->getServerTasks(), array(), 'Server tasks were correctly executed.');
    $this->assertEqual($this->getCalledServerMethods(), $expected_methods, 'Right methods were called during task execution.');
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
