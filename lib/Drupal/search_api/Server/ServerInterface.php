<?php
/**
 * @file
 * Contains \Drupal\search_api\Server\ServerInterface.
 */

namespace Drupal\search_api\Server;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Config\Entity\ConfigEntityInterface;

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

}
