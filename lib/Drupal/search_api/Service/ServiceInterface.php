<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServiceInterface.
 */

namespace Drupal\search_api\Service;

use Drupal\search_api\Plugin\ConfigurablePluginInterface;
use Drupal\search_api\Index\IndexInterface;

/**
 * Interface defining the methods search services have to implement.
 */
interface ServiceInterface extends ConfigurablePluginInterface {

  /**
   * Determine whether the service supports a given feature.
   *
   * Features are optional extensions to Search API functionality and usually
   * defined and used by third-party modules.
   *
   * There are currently three features defined directly in the Search API:
   * <ul>
   *   <li>"search_api_facets", by the search_api_facetapi module.</li>
   *   <li>"search_api_facets_operator_or", also by the search_api_facetapi module.</li>
   *   <li>"search_api_mlt", by the search_api_views module.</li>
   * </ul>
   *
   * @param string $feature
   *   The name of the optional feature.
   *
   * @return boolean
   *   TRUE if the service knows and supports the specified feature, otherwise
   *   FALSE.
   */
  public function supportsFeature($feature);

  /**
   * Add a new index to the service.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the index was added, otherwise FALSE.
   */
  public function addIndex(IndexInterface $index);

  /**
   * Update index of the service.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function updateIndex(IndexInterface $index);

  /**
   * Determine whether the service contains the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the index exists, otherwise FALSE.
   */
  public function hasIndex(IndexInterface $index);

  /**
   * Remove an index from the service.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function removeIndex(IndexInterface $index);

  /**
   * Index the specified items.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param array $items
   *   An associtiave array of ItemInterface objects, keyed by the item ID.
   */
  public function indexItems(IndexInterface $index, array $items);

  /**
   * Delete the specified items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param array $ids
   *   An array of item IDs.
   *
   * @return boolean
   *   TRUE if the items were deleted, otherwise FALSE.
   */
  public function deleteItems(IndexInterface $index, array $ids);

  /**
   * Delete all the items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return boolean
   *   TRUE if the all items were deleted, otherwise FALSE.
   */
  public function deleteAllItems(IndexInterface $index);

}
