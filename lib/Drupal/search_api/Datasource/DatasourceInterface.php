<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourceInterface.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\search_api\Index\IndexInterface;

/**
 * Interface which desribes a datasource.
 */
interface DatasourceInterface extends PluginInspectionInterface {

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
   * @return \Drupal\search_api\Datasource\DatasourceItemInterface|NULL
   *   An instance of DatasourceItemInterface if present, otherwise NULL.
   */
  public function load($id);

  /**
   * Load multiple items.
   *
   * @param array $ids
   *   An array of item IDs.
   *
   * @return array
   *   An associative array of DatasourceItemInterface objects, keyed by their
   *   ID.
   */
  public function loadMultiple(array $ids);

  /**
   * Add an index tracker.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function addIndexTracker(IndexInterface $index);

  /**
   * Determine whether an index tracker is present.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the index is being tracked, otherwise FALSE.
   */
  public function hasIndexTracker(IndexInterface $index);

  /**
   * Get the index tracker.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return \Drupal\search_api\Index\IndexTrackerInterface|NULL
   *   An instance of IndexTrackerInterface if present, otherwise NULL.
   */
  public function getIndexTracker(IndexInterface $index);

  /**
   * Remove an index tracker.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function removeIndexTracker(IndexInterface $index);

}
