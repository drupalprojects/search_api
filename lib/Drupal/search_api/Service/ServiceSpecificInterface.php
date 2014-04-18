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
   * Adds a new index to this server.
   *
   * If the index was already added to the server, the object should treat this
   * as if removeIndex() and then addIndex() were called.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index to add.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while adding the index.
   */
  public function addIndex(IndexInterface $index);

  /**
   * Notifies the server that an index attached to it has been changed.
   *
   * If any user action is necessary as a result of this, the method should
   * use drupal_set_message() to notify the user.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The updated index.
   *
   * @return bool
   *   TRUE, if this change affected the server in any way that forces it to
   *   re-index the content. FALSE otherwise.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while reacting to the change.
   */
  public function updateIndex(IndexInterface $index);

  /**
   * Removes an index from this server.
   *
   * This might mean that the index has been deleted, or reassigned to a
   * different server. If you need to distinguish between these cases, inspect
   * $index->server.
   *
   * If the index wasn't added to the server, the method call should be ignored.
   *
   * Implementations of this method should also check whether $index->read_only
   * is set, and don't delete any indexed data if it is.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   Either an object representing the index to remove, or its machine name
   *   (if the index was completely deleted).
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while removing the index.
   */
  public function removeIndex(IndexInterface $index);

  /**
   * Indexes the specified items.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The search index for which items should be indexed.
   * @param array $items
   *   An array of items to be indexed, keyed by their IDs. They are represented
   *   as element arrays. The settings of these arrays (i.e., keys prefixed with
   *   '#') are arbitrary meta information (only "#item", containing the loaded
   *   item object (if available), and "#datasource", the datasource of the item
   *   are defined) and the children map field identifiers to arrays containing
   *   the following keys:
   *   - type: One of the data types recognized by the Search API, or the
   *     special type "tokens" for tokenized fulltext fields.
   *   - original_type: The original type of the property, as defined by the
   *     datasource controller for the index's item type.
   *   - value: An array of values to be indexed for this field. The service
   *     class should also index the first value separately, for single-value
   *     use (e.g., sorting).
   *   - boost: (optional) The (decimal) boost to assign to the field. Usually
   *     only used for fulltext fields. Should default to 1.
   *
   *   An example of a $items arrays passed to this method would therefore look
   *   as follows:
   *
   *   @code
   *   $items = array(
   *     'some_item_id' => array(
   *       '#item' => $item1,// object
   *       'id' => array(
   *         'type' => 'integer',
   *         'original_type' => 'field_item:integer',
   *         'value' => 1,
   *       ),
   *       'field_text' => array(
   *         'type' => 'text',
   *         'original_type' => 'field_item:string',
   *         'value' => 'This is some text on the item.',
   *         'boost' => 4.0,
   *       ),
   *     ),
   *     'another_item_id' => array(
   *       '#item' => $item2,// object
   *       'id' => array(
   *         'type' => 'integer',
   *         'original_type' => 'field_item:integer',
   *         'value' => 2,
   *       ),
   *       'field_text' => array(
   *         'type' => 'text',
   *         'original_type' => 'field_item:string',
   *         'value' => 'This is some text on the second item.',
   *         'boost' => 4.0,
   *       ),
   *     ),
   *   );
   *   @endcode
   *
   *   The special field "search_api_language" contains the item's language and
   *   is always indexed.
   *
   *   The value of fields with the "tokens" type is an array of tokens. Each
   *   token is an array containing the following keys:
   *   - value: The word that the token represents.
   *   - score: A score for the importance of that word.
   *
   * @return array
   *   An array of the ids of all items that were successfully indexed.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If indexing was prevented by a fundamental configuration error.
   *
   * @see \Drupal\Core\Render\Element::child()
   */
  public function indexItems(IndexInterface $index, array $items);

  /**
   * Deletes the specified items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index for which items should be deleted.
   * @param array $ids
   *   An array of item IDs.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while trying to delete the items.
   */
  public function deleteItems(IndexInterface $index, array $ids);

  /**
   * Deletes all the items from the index.
   *
   * @param \Drupal\search_api\Index\IndexInterface|null $index
   *   The index for which items should be deleted, or NULL to delete all items
   *   on this server.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred while trying to delete the items.
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
