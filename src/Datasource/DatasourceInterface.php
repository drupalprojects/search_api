<?php

/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourceInterface.
 */

namespace Drupal\search_api\Datasource;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Plugin\IndexPluginInterface;

/**
 * Describes a type of search items that can be indexed.
 *
 * A datasource is used to abstract the type of data that can be indexed and
 * searched with the Search API. Content entities are supported by default (with
 * the \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource
 * datasource), but others can be added by other modules. Datasources provide
 * all kinds of metadata for search items of their type, as well as loading and
 * viewing functionality.
 *
 * Modules providing new datasources are also responsible for calling the
 * appropriate track*() methods on all indexes that use that datasource when an
 * item of that type is inserted, updated or deleted.
 *
 * Note that the two load methods in this interface do not receive the normal
 * combined item IDs (that also include the datasource ID), but only the raw,
 * datasource-specific IDs.
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
   *   The datasource-specific ID of the item.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|NULL
   *   The loaded item if it could be found, NULL otherwise.
   */
  public function load($id);

  /**
   * Loads multiple items.
   *
   * @param array $ids
   *   An array of datasource-specific item IDs.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface[]
   *   An associative array of loaded items, keyed by their
   *   (datasource-specific) IDs.
   */
  public function loadMultiple(array $ids);

  /**
   * Retrieves the unique ID of an item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this controller's type.
   *
   * @return string
   *   The datasource-internal, unique ID of the item.
   */
  public function getItemId(ComplexDataInterface $item);

  /**
   * Retrieves a human-readable label for an item.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this controller's type.
   *
   * @return string|null
   *   Either a human-readable label for the item, or NULL if none is available.
   */
  public function getItemLabel(ComplexDataInterface $item);

  /**
   * Retrieves a URL at which the item can be viewed on the web.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   An item of this DataSource's type.
   *
   * @return \Drupal\Core\Url|null
   *   Either an object representing the URL of the given item, or NULL if the
   *   item has no URL of its own.
   *
   */
  public function getItemUrl(ComplexDataInterface $item);

  /**
   * Returns view mode info for this item type.
   *
   * @return array
   *   An associative array, keyed by the view mode names, and with the values
   *   being associative arrays with at least the following keys:
   *   - label: A human-readable label for the view mode.
   */
  public function getViewModes();

  /**
   * Returns the render array for the provided item and view mode.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The item to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the item.
   * @param string|null $langcode
   *   (optional) For which language the item should be rendered. Defaults to
   *   the language the item has been loaded in.
   *
   * @return array
   *   A render array for the item.
   *
   * @throws \InvalidArgumentException
   *   Can be thrown when the set of parameters is inconsistent.
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL);

  /**
   * Returns the render array for the provided items and view mode.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface[] $items
   *   The items to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the items.
   * @param string|null $langcode
   *   (optional) For which language the items should be rendered. Defaults to
   *   the language each item has been loaded in.
   *
   * @return array
   *   A render array for the items.
   *
   * @throws \InvalidArgumentException
   *   Can be thrown when the set of parameters is inconsistent.
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL);

  /**
   * Gets the entity type ID.
   *
   * @return string|null
   *   Entity type ID if the data source contains entities.
   *
   */
  public function getEntityTypeId();

  /**
   * Returns item IDs from this datasource.
   *
   * Returns all items IDs by default. Allows for simple paging by passing
   * along a limit and a pointer from where it should start.
   *
   * @param int $limit
   *   The amount of items to return
   * @param string $from
   *   The pointer as from where we want to start returning.
   *
   * @return array
   *   An array with item identifiers
   *
   * @todo Change to single $page parameter.
   */
  public function getItemIds($limit = -1, $from = NULL);

}
