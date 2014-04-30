<?php

/**
 * @file
 * Contains \Drupal\search_api\Backend\BackendInterface.
 */

namespace Drupal\search_api\Backend;

use Drupal\search_api\Plugin\ConfigurablePluginInterface;

/**
 * Interface defining the methods search backends have to implement.
 *
 * Consists of general plugin methods and the backend-specific methods defined
 * in \Drupal\search_api\Backend\BackendSpecificInterface, as well as special
 * CRUD "hook" methods that cannot be present on the server entity (which also
 * implements \Drupal\search_api\Backend\BackendSpecificInterface.
 */
interface BackendInterface extends ConfigurablePluginInterface, BackendSpecificInterface {

  /**
   * Reacts to the server's creation.
   *
   * Called once, when the server is first created. Allows the backend class to
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
