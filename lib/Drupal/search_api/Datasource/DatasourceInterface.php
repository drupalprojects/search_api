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
   * Retrieves the properties exposed by the underlying complex data type.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An associative array of property data types, keyed by the property name.
   */
  public function getPropertyDefinitions();

  /**
   * Loads an item.
   *
   * @param mixed $id
   *   The ID of an item.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|NULL
   *   The loaded item if it could be found, NULL otherwise.
   */
  public function load($id);

  /**
   * Loads multiple items.
   *
   * @param array $ids
   *   An array of item IDs.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface[]
   *   An associative array of loaded items, keyed by their IDs.
   */
  public function loadMultiple(array $ids);

  /**
   * Retrieves the tracker for this datasource.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerInterface
   *   A tracker for this datasource.
   */
  public function getTracker();

}
