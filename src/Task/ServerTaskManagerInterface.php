<?php

namespace Drupal\search_api\Task;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;

/**
 * Defines the interface for the server task manager.
 */
interface ServerTaskManagerInterface {

  /**
   * Retrieves the number of pending server tasks.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be counted, or NULL to count all
   *   tasks.
   *
   * @return int
   *   The number of tasks pending for this server, or in total.
   */
  public function getCount(ServerInterface $server = NULL);

  /**
   * Checks for pending tasks on one or all enabled search servers.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be executed. If not given, the
   *   tasks for all enabled servers are executed.
   *
   * @return bool
   *   TRUE if all tasks (for the specific server, if $server was given) were
   *   executed successfully, or if there were no tasks. FALSE if there are
   *   still pending tasks.
   */
  public function execute(ServerInterface $server = NULL);

  /**
   * Sets a batch for executing server tasks.
   *
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) The server whose tasks should be executed. If not given, the
   *   tasks for all enabled servers are executed.
   */
  public function setExecuteBatch(ServerInterface $server = NULL);

  /**
   * Adds an entry into a server's list of pending tasks.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The server for which a task should be remembered.
   * @param string $type
   *   The type of task to perform.
   * @param \Drupal\search_api\IndexInterface|string|null $index
   *   (optional) If applicable, the index to which the task pertains (or its
   *   ID).
   * @param mixed $data
   *   (optional) If applicable, some further data necessary for the task.
   */
  public function add(ServerInterface $server, $type, IndexInterface $index = NULL, $data = NULL);

  /**
   * Removes pending server tasks from the list.
   *
   * @param array|null $ids
   *   (optional) The IDs of the pending server tasks to delete. Set to NULL
   *   to not filter by IDs.
   * @param \Drupal\search_api\ServerInterface|null $server
   *   (optional) A server for which the tasks should be deleted. Set to NULL to
   *   delete tasks from all servers.
   * @param \Drupal\search_api\IndexInterface|string|null $index
   *   (optional) An index (or its ID) for which the tasks should be deleted.
   *   Set to NULL to delete tasks for all indexes.
   * @param string[]|null $types
   *   (optional) The types of tasks that should be deleted, or NULL to delete
   *   tasks regardless of type.
   */
  public function delete(array $ids = NULL, ServerInterface $server = NULL, $index = NULL, array $types = NULL);

}
