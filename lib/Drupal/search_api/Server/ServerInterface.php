<?php
/**
 * @file
 * Contains \Drupal\search_api\Server\ServerInterface.
 */

namespace Drupal\search_api\Server;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Defines the interface for server entities.
 */
interface ServerInterface extends ConfigEntityInterface {

  /**
   * Get the description.
   *
   * @return string
   *   The description of the server.
   */
  public function getDescription();

  /**
   * Determine whether the service is valid.
   *
   * @return boolean
   *   TRUE if the service is valid, otherwise FALSE.
   */
  public function hasValidService();

  /**
   * Get the service.
   *
   * @return \Drupal\search_api\Service\ServiceInterface
   *   An instance of ServiceInterface.
   */
  public function getService();

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

}
