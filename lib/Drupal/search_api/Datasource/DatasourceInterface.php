<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourceInterface.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include required classes and interfaces.
 */
use Drupal\search_api\Plugin\ConfigurablePluginInterface;

/**
 * Interface which desribes a datasource.
 */
interface DatasourceInterface extends ConfigurablePluginInterface {

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
   * Determine whether a datasource tracker for the given index exists.
   *
   * @return boolean
   *   TRUE if the tracker exists, otherwise FALSE.
   */
  public function hasTracker();

  /**
   * Get a datasource tracker.
   *
   * @return \Drupal\search_api\Datasource\Tracker\TrackerInterface|NULL
   *   An instance of TrackerInterface if present, otherwise NULL.
   */
  public function getTracker();

  /**
   * Add a datasource tracker.
   *
   * @return boolean
   *   TRUE if the tracker was added, otherwise FALSE.
   */
  public function addTracker();

  /**
   * Remove a datasource tracker.
   *
   * @return boolean
   *   TRUE if removed, otherwise FALSE.
   */
  public function removeTracker();

}
