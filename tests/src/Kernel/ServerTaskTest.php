<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;

/**
 * Tests whether the server task system works correctly.
 *
 * @group search_api
 */
class ServerTaskTest extends KernelTestBase {

  /**
   * The test server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * The test index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The content entity datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'user',
    'search_api',
    'search_api_test_backend',
  );

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The server task manager to use for the tests.
   *
   * @var \Drupal\search_api\Task\ServerTaskManagerInterface
   */
  protected $serverTaskManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->state = $this->container->get('state');

    $this->installEntitySchema('user');
    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    // Create a test server.
    $this->server = Server::create(array(
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test_backend',
    ));
    $this->server->save();

    // Create a test index.
    $this->index = Index::create(array(
      'name' => 'Test index',
      'id' => 'test_index',
      'status' => 1,
      'datasource_settings' => array(
        'entity:user' => array(
          'plugin_id' => 'entity:user',
          'settings' => array(),
        ),
      ),
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
      'server' => $this->server->id(),
      'options' => array('index_directly' => FALSE),
    ));
    $this->index->save();

    $this->serverTaskManager = $this->container->get('search_api.server_task_manager');

    // Reset the list of called backend methods.
    $this->getCalledBackendMethods();
  }

  /**
   * Tests task system integration for the server's addIndex() method.
   */
  public function testAddIndex() {
    // Since we want to add the index, we should first remove it (even though it
    // shouldn't matter – just for logic consistency).
    $this->index->setServer(NULL);
    $this->index->save();

    // Set exception for addIndex() and reset the list of successful backend
    // method calls.
    $this->state->set('search_api_test_backend.exception.addIndex', TRUE);
    $this->getCalledBackendMethods();

    // Try to add the index.
    $this->server->addIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'addIndex correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'addIndex';
    }
    $this->assertTrue(!empty($task_created), 'The addIndex task was successfully added.');
    if ($tasks) {
      $this->assertEquals($this->index->id(), $tasks[0]->index_id, 'The right index ID was used for the addIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->assertTrue(TRUE, "Second task ('updateIndex') was added.");
      $this->assertEquals('addIndex', $tasks[0]->type, 'First task stayed the same.');
      $this->assertEquals('updateIndex', $tasks[1]->type, 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let addIndex() succeed again, then trigger the task execution with a cron
    // run.
    $this->state->set('search_api_test_backend.exception.addIndex', FALSE);
    search_api_cron();
    $this->assertEquals(array(), $this->getServerTasks(), 'Server tasks were correctly executed.');
    $this->assertEquals(array('addIndex', 'updateIndex'), $this->getCalledBackendMethods(), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's updateIndex() method.
   */
  public function testUpdateIndex() {
    // Set exception for updateIndex().
    $this->state->set('search_api_test_backend.exception.updateIndex', TRUE);

    // Try to update the index.
    $this->server->updateIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'updateIndex correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'updateIndex';
    }
    $this->assertTrue(!empty($task_created), 'The updateIndex task was successfully added.');
    if ($tasks) {
      $this->assertEquals($this->index->id(), $tasks[0]->index_id, 'The right index ID was used for the updateIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->deleteAllIndexItems($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'deleteAllIndexItems was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->assertTrue(TRUE, "Second task ('deleteAllIndexItems') was added.");
      $this->assertEquals('updateIndex', $tasks[0]->type, 'First task stayed the same.');
      $this->assertEquals('deleteAllIndexItems', $tasks[1]->type, 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (deleteAllIndexItems) was not added.");
    }

    // Let updateIndex() succeed again, then trigger the task execution with a
    // call to indexItems().
    $this->state->set('search_api_test_backend.exception.updateIndex', FALSE);
    $this->server->indexItems($this->index, array());

    $expected_methods = array(
      'updateIndex',
      'deleteAllIndexItems',
      'indexItems',
    );
    $this->assertEquals(array(), $this->getServerTasks(), 'Server tasks were correctly executed.');
    $this->assertEquals($expected_methods, $this->getCalledBackendMethods(), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's removeIndex() method.
   */
  public function testRemoveIndex() {
    // Set exception for updateIndex() and removeIndex().
    $this->state->set('search_api_test_backend.exception.updateIndex', TRUE);
    $this->state->set('search_api_test_backend.exception.removeIndex', TRUE);

    // First try to update the index and fail. Then try to remove it and check
    // that the tasks were set correctly.
    $this->server->updateIndex($this->index);
    $this->server->removeIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'updateIndex and removeIndex correctly threw exceptions.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'removeIndex';
    }
    $this->assertTrue(!empty($task_created), 'The removeIndex task was successfully added and other tasks removed.');
    if ($tasks) {
      $this->assertEquals($this->index->id(), $tasks[0]->index_id, 'The right index ID was used for the removeIndex task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    try {
      $this->server->indexItems($this->index, array());
      $this->fail('Pending server tasks did not prevent indexing of items.');
    }
    catch (SearchApiException $e) {
      $args['%index'] = $this->index->label();
      $expected_message = new FormattableMarkup('Could not index items on index %index because pending server tasks could not be executed.', $args);
      $this->assertEquals($expected_message, $e->getMessage(), 'Pending server tasks prevented indexing of items.');
    }
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'indexItems was not executed.');
    $tasks = $this->getServerTasks();
    $this->assertEquals(1, count($tasks), 'No task added for indexItems.');

    // Let removeIndex() succeed again, then trigger the task execution with a
    // cron run.
    $this->state->set("search_api_test_backend.exception.removeIndex", FALSE);
    search_api_cron();
    $this->assertEquals(array(), $this->getServerTasks(), 'Server tasks were correctly executed.');
    $this->assertEquals(array('removeIndex'), $this->getCalledBackendMethods(), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the server's deleteItems() method.
   */
  public function testDeleteItems() {
    // Set exception for deleteItems().
    $this->state->set('search_api_test_backend.exception.deleteItems', TRUE);

    // Try to update the index.
    $this->server->deleteItems($this->index, array());
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'deleteItems correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'deleteItems';
    }
    $this->assertTrue(!empty($task_created), 'The deleteItems task was successfully added.');
    if ($tasks) {
      $this->assertEquals($this->index->id(), $tasks[0]->index_id, 'The right index ID was used for the deleteItems task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->assertTrue(TRUE, "Second task ('updateIndex') was added.");
      $this->assertEquals('deleteItems', $tasks[0]->type, 'First task stayed the same.');
      $this->assertEquals('updateIndex', $tasks[1]->type, 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let deleteItems() succeed again, then trigger the task execution
    // with a cron run.
    $this->state->set('search_api_test_backend.exception.deleteItems', FALSE);
    search_api_cron();
    $this->assertEquals(array(), $this->getServerTasks(), 'Server tasks were correctly executed.');
    $this->assertEquals(array('deleteItems', 'updateIndex'), $this->getCalledBackendMethods(), 'Right methods were called during task execution.');
  }

  /**
   * Tests task system integration for the deleteAllIndexItems() method.
   */
  public function testDeleteAllIndexItems() {
    // Set exception for deleteAllIndexItems().
    $this->state->set('search_api_test_backend.exception.deleteAllIndexItems', TRUE);

    // Try to update the index.
    $this->server->deleteAllIndexItems($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'deleteAllIndexItems correctly threw an exception.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 1) {
      $task_created = $tasks[0]->type === 'deleteAllIndexItems';
    }
    $this->assertTrue(!empty($task_created), 'The deleteAllIndexItems task was successfully added.');
    if ($tasks) {
      $this->assertEquals($this->index->id(), $tasks[0]->index_id, 'The right index ID was used for the deleteAllIndexItems task.');
    }

    // Check whether other task-system-integrated methods now fail, too.
    $this->server->updateIndex($this->index);
    $this->assertEquals(array(), $this->getCalledBackendMethods(), 'updateIndex was not executed.');
    $tasks = $this->getServerTasks();
    if (count($tasks) == 2) {
      $this->assertTrue(TRUE, "Second task ('updateIndex') was added.");
      $this->assertEquals('deleteAllIndexItems', $tasks[0]->type, 'First task stayed the same.');
      $this->assertEquals('updateIndex', $tasks[1]->type, 'New task was queued as last.');
    }
    else {
      $this->fail("Second task (updateIndex) was not added.");
    }

    // Let deleteAllIndexItems() succeed again, then trigger the task execution
    // with a call to indexItems().
    $this->state->set('search_api_test_backend.exception.deleteAllIndexItems', FALSE);
    $this->server->indexItems($this->index, array());

    $expected_methods = array(
      'deleteAllIndexItems',
      'updateIndex',
      'indexItems',
    );
    $this->assertEquals(array(), $this->getServerTasks(), 'Server tasks were correctly executed.');
    $this->assertEquals($expected_methods, $this->getCalledBackendMethods(), 'Right methods were called during task execution.');
  }

  /**
   * Verifies that no more than 100 items will be executed at once.
   */
  public function testTaskCountLimit() {
    // Create 100 tasks.
    for ($i = 0; $i < 101; ++$i) {
      $this->serverTaskManager->add($this->server, 'deleteItems', $this->index, array(''));
    }

    // Verify that a new operation cannot be executed.
    $this->server->updateIndex($this->index);

    $methods = $this->getCalledBackendMethods();
    $this->assertCount(100, $methods, '100 pending tasks were executed upon new operation.');
    $filter = function ($method) { return $method != 'deleteItems'; };
    $this->assertEmpty(array_filter($methods, $filter), 'The new operation was not executed.');
    $this->assertEquals(2, $this->serverTaskManager->getCount($this->server), 'A task was created for the new operation.');
  }

  /**
   * Tests the correct automatic removal of tasks upon certain operations.
   */
  public function testAutomaticTaskRemoval() {
    // Create a second server and index and add tasks for them.
    $server2 = Server::create(array(
      'name' => 'Test Server 2',
      'id' => 'test_server_2',
      'status' => 1,
      'backend' => 'search_api_test_backend',
    ));
    $server2->save();
    $this->serverTaskManager->add($server2, 'removeIndex', $this->index);

    $index_values = $this->index->toArray();
    unset($index_values['uuid']);
    $index_values['id'] = 'test_index_2';
    $index2 = Index::create($index_values);
    $index2->save();
    // Reset the called backend methods.
    $this->getCalledBackendMethods();

    // Verify that adding an index ignores all tasks related to that index.
    $this->addTasks($index2);
    $this->server->addIndex($this->index);
    $this->assertEquals(array('addIndex', 'addIndex'), $this->getCalledBackendMethods(), 'Re-adding an index ignored all its tasks.');
    $this->assertEquals(0, $this->serverTaskManager->getCount($this->server), 'No pending tasks for server.');
    $this->assertEquals(1, $this->serverTaskManager->getCount(), 'The tasks of other servers were not touched.');

    // Verify that removing an index ignores all tasks related to that index.
    $this->addTasks($index2);
    $this->server->removeIndex($this->index);
    $this->assertEquals(array('addIndex', 'removeIndex'), $this->getCalledBackendMethods(), 'Removing an index ignored all its tasks.');
    $this->assertEquals(0, $this->serverTaskManager->getCount($this->server), 'No pending tasks for server.');
    $this->assertEquals(1, $this->serverTaskManager->getCount(), 'The tasks of other servers were not touched.');

    // Verify that deleting all of an index's items ignores all other deletion
    // tasks related to that index.
    $this->addTasks($index2);
    $this->server->deleteAllIndexItems($this->index);
    $this->assertEquals(array('addIndex', 'removeIndex', 'addIndex', 'updateIndex', 'deleteAllIndexItems'), $this->getCalledBackendMethods(), 'Deleting all items of an index ignored all its deletion tasks.');
    $this->assertEquals(0, $this->serverTaskManager->getCount($this->server), 'No pending tasks for server.');
    $this->assertEquals(1, $this->serverTaskManager->getCount(), 'The tasks of other servers were not touched.');

    // Verify that removing all items from the server automatically removes all
    // item deletion tasks as well.
    $this->addTasks($index2);
    $this->server->deleteAllItems();
    // deleteAllIndexItems() is called twice – once for each index.
    $this->assertEquals(array('deleteAllIndexItems', 'deleteAllIndexItems'), $this->getCalledBackendMethods(), "Deleting all items from a server didn't execute any tasks.");
    $this->assertEquals(4, $this->serverTaskManager->getCount($this->server), 'Deleting all items from a server removed all its item deletion tasks.');
    $this->assertEquals(5, $this->serverTaskManager->getCount(), 'The tasks of other servers were not touched.');

    // Verify that deleting a server also deletes all of its tasks.
    $this->addTasks($index2);
    $this->state->set('search_api_test_backend.exception.addIndex', TRUE);
    $this->state->set('search_api_test_backend.exception.updateIndex', TRUE);
    $this->state->set('search_api_test_backend.exception.removeIndex', TRUE);
    $this->state->set('search_api_test_backend.exception.deleteItems', TRUE);
    $this->state->set('search_api_test_backend.exception.deleteAllIndexItems', TRUE);
    $this->server->delete();
    $this->assertEquals(0, $this->serverTaskManager->getCount($this->server), 'Upon server deletion, all of its tasks were deleted, too.');
    $this->assertEquals(1, $this->serverTaskManager->getCount(), 'The tasks of other servers were not touched.');
  }

  /**
   * Adds one task of each type for this test's server.
   *
   * @param \Drupal\search_api\IndexInterface $second_index
   *   A second index, for which one additional "addIndex" task is created.
   */
  protected function addTasks(IndexInterface $second_index) {
    $this->serverTaskManager->add($this->server, 'addIndex', $second_index);
    $this->serverTaskManager->add($this->server, 'removeIndex', $this->index);
    $this->serverTaskManager->add($this->server, 'addIndex', $this->index);
    $this->serverTaskManager->add($this->server, 'updateIndex', $this->index);
    $this->serverTaskManager->add($this->server, 'deleteItems', $this->index, array());
    $this->serverTaskManager->add($this->server, 'deleteAllIndexItems', $this->index);
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
  protected function getCalledBackendMethods($reset = TRUE) {
    $key = 'search_api_test_backend.methods_called.' . $this->server->id();
    $methods_called = $this->state->get($key, array());
    if ($reset) {
      $this->state->delete($key);
    }
    return $methods_called;
  }

  /**
   * Retrieves the tasks set on the test server.
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
