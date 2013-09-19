<?php
/**
 * @file
 * Contains \Drupal\search_api\Index\IndexInterface.
 */

namespace Drupal\search_api\Index;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines the interface for index entities.
 */
interface IndexInterface extends ConfigEntityInterface {

  /**
   * Get the index description.
   *
   * @return string
   *   The description of this index.
   */
  public function getDescription();

  /**
   * Determine whether this index is read-only.
   *
   * @return boolean
   *   TRUE if this index is read-only, otherwise FALSE.
   */
  public function isReadOnly();

  /**
   * Get an option.
   *
   * @param string $name
   *   The name of an option.
   *
   * @return mixed
   *   The value of the option.
   */
  public function getOption($name);

  /**
   * Get a list of all options.
   *
   * @return array
   *   An associative array of option values, keyed by the option name.
   */
  public function getOptions();

  /**
   * Determine whether the server is valid.
   *
   * @return boolean
   *   TRUE if the server is valid, otherwise FALSE.
   */
  public function hasValidServer();

  /**
   * Get the server.
   *
   * @return \Drupal\search_api\Server\ServerInterface
   *   An instance of ServerInterface.
   */
  public function getServer();

  /**
   * Set the server.
   *
   * @param \Drupal\search_api\Index\ServerInterface|NULL $server
   *   An instance of ServerInterface or NULL.
   */
  public function setServer(ServerInterface $server = NULL);

}
