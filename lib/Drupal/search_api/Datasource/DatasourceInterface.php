<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourceInterface.
 */

namespace Drupal\search_api\Datasource;

use Drupal\search_api\Plugin\IndexPluginInterface;

/**
 * Describes a datasource.
 */
interface DatasourceInterface extends IndexPluginInterface {

  /**
   * Get the properties exposed by the underlying complex data type.
   *
   * @return array
   *   An associative array of property data types, keyed by the property name.
   */
  public function getPropertyInfo();

  /**
   * Load an item.
   *
   * @param mixed $id
   *   The ID of an item.
   *
   * @return \Drupal\search_api\Datasource\Item\ItemInterface|NULL
   *   An instance of ItemInterface if present, otherwise NULL.
   */
  public function load($id);

  /**
   * Load multiple items.
   *
   * @param array $ids
   *   An array of item IDs.
   *
   * @return array
   *   An associative array of ItemInterface objects, keyed by their
   *   ID.
   */
  public function loadMultiple(array $ids);

  /**
   * Get the status information.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerStatusInterface
   *   An instance of TrackerStatusInterface.
   */
  public function getStatus();

}
