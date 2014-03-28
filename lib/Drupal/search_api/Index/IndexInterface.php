<?php
/**
 * @file
 * Contains \Drupal\search_api\Index\IndexInterface.
 */

namespace Drupal\search_api\Index;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines the interface for index entities.
 */
interface IndexInterface extends ConfigEntityInterface {

  /**
   * Retrieves the index description.
   *
   * @return string
   *   The description of this index.
   */
  public function getDescription();

  /**
   * Determine whether this index is read-only.
   *
   * @return boolean
   *   TRUE if this index is read-only, otherwise FALSE.
   */
  public function isReadOnly();

  /**
   * Get the cache ID prefix used for this index's caches.
   *
   * @param $type
   *   The type of cache. Currently only "fields" is used.
   *
   * @return
   *   The cache ID (prefix) for this index's caches.
   */
  public function getCacheId($type = 'fields');

  /**
   * Retrieves an option.
   *
   * @param string $name
   *   The name of an option.
   * @param mixed $default
   *   The value return if the option wasn't set.
   *
   * @return mixed
   *   The value of the option.
   */
  public function getOption($name, $default = NULL);

  /**
   * Retrieves an array of all options.
   *
   * @return array
   *   An associative array of option values, keyed by the option name.
   */
  public function getOptions();

  /**
   * Sets the options.
   *
   */
  public function setOptions($options);

  /**
   * Sets an option.
   *
   * @param string $name
   *   The name of an option.
   * @param $option
   *   The new option.
   *
   * @return mixed
   *   The value of the option.
   */
  public function setOption($name, $option);

  /**
   * Determine whether the datasource is valid.
   *
   * @return boolean
   *   TRUE if the datasource is valid, otherwise FALSE.
   */
  public function hasValidDatasource();

  /**
   * Retrieves the datasource plugin's ID.
   *
   * @return string
   *   The ID of the datasource plugin used by this index.
   */
  public function getDatasourceId();

  /**
   * Retrieves the datasource plugin.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface
   *   An instance of DatasourceInterface.
   */
  public function getDatasource();

  /**
   * Determines whether the server is valid.
   *
   * @return boolean
   *   TRUE if the server is valid, otherwise FALSE.
   */
  public function hasValidServer();

  /**
   * Retrieves the ID of the server the index is attached to.
   *
   * @return string|null
   *   The index's server's machine name, or NULL if the index doesn't have a
   *   server.
   */
  public function getServerId();

  /**
   * Retrieves the server the index is attached to.
   *
   * @return \Drupal\search_api\Server\ServerInterface|null
   *   An instance of ServerInterface, or NULL if the index doesn't have a
   *   server.
   */
  public function getServer();

  /**
   * Sets the server the index is attached to
   *
   * @param \Drupal\search_api\Server\ServerInterface|null $server
   *   The server to move this index to, or NULL.
   */
  public function setServer(ServerInterface $server = NULL);

  /**
   * Loads all enabled processors for this index in proper order.
   *
   * @param bool $all
   *   Also include non-active processors
   * @param string $sortBy
   *
   * @return \Drupal\search_api\Processor\ProcessorInterface[]
   *   All enabled processors for this index, as
   *   \Drupal\search_api\Plugin\search_api\ProcessorInterface objects.
   */
  public function getProcessors($all = FALSE, $sortBy = 'weight');

  /**
   * Preprocesses data items for indexing.
   *
   * Lets all enabled processors for this index preprocess the indexed data.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing.
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocesses a search query.
   *
   * Lets all enabled processors for this index preprocess the search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(QueryInterface $query);

  /**
   * Postprocesses search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results. See
   *   \Drupal\search_api\Plugin\search_api\QueryInterface::execute() for the
   *   detailed format.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query);

  /**
   * Returns a list of all known fields for this index.
   *
   * @param bool $only_indexed
   *   (optional) Return only indexed fields, not all known fields.
   * @param bool $get_additional
   *   (optional) Return not only known/indexed fields, but also related
   *   entities whose fields could additionally be added to the index.
   *
   * @return array
   *   An array of all known fields for this index. Keys are the field
   *   identifiers, the values are arrays for specifying the field settings. The
   *   structure of those arrays looks like this:
   *   - name: The human-readable name for the field.
   *   - description: A description of the field, if available.
   *   - indexed: Boolean indicating whether the field is indexed or not.
   *   - type: The type set for this field. One of the types returned by
   *     search_api_default_field_types().
   *   - real_type: (optional) If a custom data type was selected for this
   *     field, this type will be stored here, and "type" contain the fallback
   *     default data type.
   *   - boost: A boost value for terms found in this field during searches.
   *     Usually only relevant for fulltext fields.
   *   - entity_type (optional): If set, the type of this field is really an
   *     entity. The "type" key will then contain "integer", meaning that
   *     servers will ignore this and merely index the entity's ID. Components
   *     displaying this field, though, are advised to use the entity label
   *     instead of the ID.
   *   If $get_additional is TRUE, this array is encapsulated in another
   *   associative array, which contains the above array under the "fields" key,
   *   and a list of related entities (field keys mapped to names) under the
   *   "additional_fields" key.
   */
  public function getFields($only_indexed = TRUE, $get_additional = FALSE);

  /**
   * Convenience method for getting all of this index's fulltext fields.
   *
   * @param bool $only_indexed
   *   (optional) If set to TRUE, only the indexed fulltext fields will be
   *   returned. Otherwise, this method will return all available fulltext
   *   fields.
   *
   * @return array
   *   An array containing all (or all indexed) fulltext fields defined for this
   *   index.
   */
  public function getFulltextFields($only_indexed = TRUE);

  /**
   * Marks all items in this index for reindexing.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function reindex();

  /**
   * Clears all items in this index and marks them for reindexing.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function clear();

  /**
   * Resets the static and stored caches associated with this index.
   */
  public function resetCaches();

  /**
   * Creates a query object for this index.
   *
   * @param array $options
   *   (optional) Associative array of options configuring this query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A query object for searching this index.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the index is currently disabled or its server doesn't exist.
   *
   * @see \Drupal\search_api\Query\QueryInterface::create()
   */
  public function query(array $options = array());

  /**
   * Get last indexed state for this index.
   *
   * @return array
   *   An array containing the last indexed state for this index. Format is
   *    { 'changed' => 0, 'item_id' => 1 }
   *
   */
  public function getLastIndexed();

  /**
   * Set last indexed state for this index.
   *
   * @param $changed
   *   The last timestamp that was indexed
   * @param $item_id
   *   The last item that was indexed
   * @return array
   *   An array containing the stored last indexed state for this index. Format is
   *    { 'changed' => 0, 'item_id' => 1 }
   */
  public function setLastIndexed($changed, $item_id);
}
