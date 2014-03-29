<?php
/**
 * @file
 * Contains \Drupal\search_api\Server\ServerInterface.
 */

namespace Drupal\search_api\Server;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Service\ServiceSpecificInterface;

/**
 * Defines the interface for server entities.
 */
interface ServerInterface extends ConfigEntityInterface, ServiceSpecificInterface {

  /**
   * Retrieves the server's description.
   *
   * @return string
   *   The description of the server.
   */
  public function getDescription();

  /**
   * Determines whether the service is valid.
   *
   * @return bool
   *   TRUE if the service is valid, otherwise FALSE.
   */
  public function hasValidService();

  /**
   * Retrieves the service.
   *
   * @return \Drupal\search_api\Service\ServiceInterface
   *   An instance of ServiceInterface.
   */
  public function getService();

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
   * Sets the service config of the service from this server.
   *
   * @param array $config
   *   the service config
   */
  public function setServicePluginConfig($config);

  /**
   * Gets the service config of the service from this server.
   *
   * @return array
   *   An array with the service config.
   */
  public function getServicePluginConfig();

}
