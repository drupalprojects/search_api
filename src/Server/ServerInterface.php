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
   * Retrieves the plugin ID of the backend of this server.
   *
   * @return string
   *   The plugin ID of the backend.
   */
  public function getBackendId();

  /**
   * Retrieves the backend.
   *
   * @return \Drupal\search_api\Backend\BackendInterface
   *   This server's backend plugin.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the backend plugin could not be retrieved.
   */
  public function getBackend();

  /**
   * Retrieves a list of indexes which use this server.
   *
   * @param array $properties
   *   (optional) Additional properties that the indexes should have.
   *
   * @return \Drupal\search_api\Index\IndexInterface[]
   *   An array of IndexInterface instances.
   */
  public function getIndexes(array $properties = array());

  /**
   * Deletes all items on this server, except those from read-only indexes.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while trying to delete the items.
   */
  public function deleteAllItems();

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

}
