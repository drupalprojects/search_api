<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServiceInterface.
 */

namespace Drupal\search_api\Service;

use Drupal\search_api\Plugin\ConfigurablePluginInterface;

/**
 * Interface defining the methods search services have to implement.
 *
 * Consists of general plugin methods and the service-specific methods defined
 * in \Drupal\search_api\Service\ServiceSpecificInterface, as well as special
 * CRUD "hook" methods that cannot be present on the server entity (which also
 * implements \Drupal\search_api\Service\ServiceSpecificInterface.
 */
interface ServiceInterface extends ConfigurablePluginInterface, ServiceSpecificInterface {

  /**
   * Reacts to the server's creation.
   *
   * Called once, when the server is first created. Allows the service class to
   * set up its necessary infrastructure.
   */
  public function postInsert();

  /**
   * Notifies this server that its fields are about to be updated.
   *
   * The server's $original property can be used to inspect the old property
   * values.
   */
  public function preUpdate();

  /**
   * Notifies this server that its fields were updated.
   *
   * The server's $original property can be used to inspect the old property
   * values.
   *
   * @return bool
   *   TRUE, if the update requires reindexing of all content on the server.
   */
  public function postUpdate();

  /**
   * Notifies this server that it is about to be deleted from the database.
   *
   * This should execute any necessary cleanup operations.
   *
   * Note that you shouldn't call the server's save() method, or any
   * methods that might do that, from inside of this method as the server isn't
   * present in the database anymore at this point.
   */
  public function preDelete();

}
