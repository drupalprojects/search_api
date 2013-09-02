<?php
/**
 * @file
 * Contains Drupal\search_api\ServerInterface.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Plugin\Type\Service\ServiceInterface;

/**
 * Defines the interface for server entities.
 */
interface ServerInterface extends ConfigEntityInterface, ServiceInterface {

  /**
   * Retrieves all indexes currently on this server.
   *
   * @param array $conditions
   *   An array in the form $field => $value with additional constraints.
   *
   * @return array
   *   The loaded indexes.
   */
  public function getIndexes(array $conditions = array());

}
