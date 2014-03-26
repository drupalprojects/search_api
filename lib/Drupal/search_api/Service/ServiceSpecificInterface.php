<?php

/**
 * @file
 * Contains \Drupal\search_api\Service\ServiceSpecificInterface.
 */

namespace Drupal\search_api\Service;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Interface defining the methods search services have to implement.
 */
interface ServiceSpecificInterface {

  /**
   * Returns additional, service-specific information about this server.
   *
   * This information will be then added to the server's "View" tab in some way.
   * In the default theme implementation this data will be output in a table
   * with two columns along with other, generic information about the server.
   *
   * @return array
   *   An array of additional server information, with each piece of information
   *   being an associative array with the following keys:
   *   - label: The human-readable label for this data.
   *   - info: The information, as HTML.
   *   - status: (optional) The status associated with this information. One of
   *     "info", "ok", "warning" or "error". Defaults to "info".
   */
  public function viewSettings();

  /**
   * Determines whether the service supports a given feature.
   *
   * Features are optional extensions to Search API functionality and usually
   * defined and used by third-party modules.
   *
   * There are currently three features defined directly in the Search API
   * project:
   * - search_api_facets, by the search_api_facetapi module.
   * - search_api_facets_operator_or, also by the search_api_facetapi module.
   * - search_api_mlt, by the search_api_views module.
   *
   * @param string $feature
   *   The name of the optional feature.
   *
   * @return bool
   *   TRUE if the service knows and supports the specified feature, otherwise
   *   FALSE.
   */
  public function supportsFeature($feature);

  /**
   * Determines whether the service supports a given add-on data type.
   *
   * @param string $type
   *   The identifier of the add-on data type.
   *
   * @return bool
   *   TRUE if the service supports the data type.
   */
  public function supportsDatatype($type);

  /**
   * Adds a new index to the service.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   *
   * @return bool
   *   TRUE if the index was added, otherwise FALSE.
   */
  public function addIndex(IndexInterface $index);

  /**
   * Reacts to a change in an index that lies on this server.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function updateIndex(IndexInterface $index);

  /**
   * Remove an index from the service.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function removeIndex(IndexInterface $index);

  /**
   * Indexes the specified items.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param array $items
   *   An associtiave array of ItemInterface objects, keyed by the item ID.
   */
  public function indexItems(IndexInterface $index, array $items);

  /**
   * Deletes the specified items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param array $ids
   *   An array of item IDs.
   *
   * @return bool
   *   TRUE if the items were deleted, otherwise FALSE.
   */
  public function deleteItems(IndexInterface $index, array $ids);

  /**
   * Deletes all the items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface|null $index
   *   An instance of IndexInterface, or NULL to delete all items on this
   *   server.
   *
   * @return bool
   *   TRUE if the all items were deleted, otherwise FALSE.
   */
  public function deleteAllItems(IndexInterface $index = NULL);

  /**
   * Executes a search on the server represented by this object.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to execute.
   *
   * @return array
   *   An associative array containing the search results, as required by
   *   \Drupal\search_api\Query\QueryInterface::execute().
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error prevented the search from completing.
   */
  public function search(QueryInterface $query);

}
