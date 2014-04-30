<?php
/**
 * @file
 * Contains \Drupal\search_api\Server\ServerInterface.
 */

namespace Drupal\search_api\Server;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Backend\BackendSpecificInterface;

/**
 * Defines the interface for server entities.
 */
interface ServerInterface extends ConfigEntityInterface, BackendSpecificInterface {

  /**
   * Retrieves the server's description.
   *
   * @return string
   *   The description of the server.
   */
  public function getDescription();

  /**
   * Determines whether the backend is valid.
   *
   * @return bool
   *   TRUE if the backend is valid, otherwise FALSE.
   */
  public function hasValidBackend();

  /**
   * Retrieves the backend.
   *
   * @return \Drupal\search_api\Backend\BackendInterface
   *   An instance of BackendInterface.
   */
  public function getBackend();

  /**
   * Retrieves a list of indexes which use this server.
   *
   * @return \Drupal\search_api\Index\IndexInterface[]
   *   An array of IndexInterface instances.
   */
  public function getIndexes();

  /**
   * Executes a search on the server represented by this object.
   *
   * @param QueryInterface $query
   *   The query to execute.
   *
   * @return array
   *   An associative array containing the search results, as required by
   *   \Drupal\search_api\Query\QueryInterface::execute().
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error prevented the search from completing.
   */
  public function search(QueryInterface $query);

  /**
   * Sets the backend config of the backend from this server.
   *
   * @param array $config
   *   the backend config
   */
  public function setBackendPluginConfig($config);

  /**
   * Gets the backend config of the backend from this server.
   *
   * @return array
   *   An array with the backend config.
   */
  public function getBackendPluginConfig();

  /**
   * Adds an entry into this server's list of pending tasks.
   *
   * @param string $type
   *   The type of task to perform.
   * @param \Drupal\search_api\Index\IndexInterface|string|null $index
   *   (optional) If applicable, the index to which the task pertains (or its
   *   machine name).
   * @param mixed $data
   *   (optional) If applicable, some further data necessary for the task.
   */
  public function tasksAdd($type, $index = NULL, $data = NULL);

  /**
   * Removes pending tasks for this server from the list.
   *
   * @param array|null $ids
   *   (optional) The IDs of the pending server tasks to delete. Set to NULL
   *   to not filter by IDs.
   * @param \Drupal\search_api\Index\IndexInterface|string|null $index
   *   (optional) An index (or its machine name) for which the tasks should be
   *   deleted. Set to NULL to delete tasks for all indexes.
   */
  public function tasksDelete(array $ids = NULL, $index = NULL);

}
