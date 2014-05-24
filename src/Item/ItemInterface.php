<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\ItemInterface.
 */

namespace Drupal\search_api\Item;

use Drupal\Core\TypedData\ComplexDataInterface;

/**
 * Represents a Search API indexing or result item.
 *
 * Traversing the object should retrieve all its fields.
 */
interface ItemInterface extends \Traversable {

  /**
   * Returns the item ID.
   *
   * @return string
   *   The ID of this item.
   */
  public function getId();

  /**
   * Returns the original complex data object this Search API item is based on.
   *
   * @param bool $load
   *   (optional) If TRUE, the object will be loaded if necessary. Otherwise,
   *   NULL will be returned if the object isn't available.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface|null
   *   NULL if $load is FALSE and the object wasn't set or loaded previously.
   *   Otherwise, the wrapped object.
   */
  public function getOriginalObject($load = TRUE);

  /**
   * Sets the original complex data object this item should be based on.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $original_object
   *   The object that should be wrapped.
   *
   * @return self
   *   The invoked object.
   */
  public function setOriginalObject(ComplexDataInterface $original_object);

  /**
   * Returns the datasource of this item.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface
   *   The datasource to which this item belongs.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the item's datasource wasn't set before and couldn't be loaded.
   */
  public function getDatasource();

  /**
   * Returns the index of this item.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The index to which this item belongs.
   */
  public function getIndex();

  /**
   * Retrieves a single field of this item.
   *
   * @param string $field_id
   *   The identifier of the field to retrieve.
   * @param bool $extract
   *   (optional) If TRUE, a missing field will be extracted from the original
   *   object. Defaults to only return the field if it was previously set with
   *   setField() or setFields().
   *
   * @return \Drupal\search_api\Item\FieldInterface|null
   *   The field object with this identifier, or NULL if the field is unknown.
   */
  public function getField($field_id, $extract = FALSE);

  /**
   * Returns the item's fields.
   *
   * @param bool $extract
   *   (optional) If TRUE, missing fields will be extracted from the original
   *   object. Defaults to only return those fields previously set with
   *   setField() or setFields().
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   An array with the fields of this item, keyed by field identifier.
   */
  public function getFields($extract = FALSE);

  /**
   * Sets one of the item's fields.
   *
   * @param string $field_id
   *   The field's identifier.
   * @param \Drupal\search_api\Item\FieldInterface|null $field
   *   (optional) The information and contents of this field. Or NULL to remove
   *   the field from the item.
   *
   * @return self
   *   The invoked object.
   */
  public function setField($field_id, FieldInterface $field = NULL);

  /**
   * Sets the item's fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   An array with the fields of this item, keyed by field identifier.
   *
   * @return self
   *   The invoked object.
   */
  public function setFields(array $fields);

  /**
   * Returns the score of the item.
   *
   * Defaults to 1 if not previously set.
   *
   * @return float
   *   The score of the item.
   */
  public function getScore();

  /**
   * Sets the score of the item.
   *
   * @param float $score
   *   The score of the item.
   *
   * @return self
   *   The invoked object.
   */
  public function setScore($score);

  /**
   * Gets the boost value of this item.
   *
   * Defaults to 1 if not previously set.
   *
   * @return float
   *   The boost value.
   */
  public function getBoost();

  /**
   * Sets the boost value of this item.
   *
   * @param float $boost
   *   The boost value to set.
   *
   * @return self
   *   The invoked object.
   */
  public function setBoost($boost);

  /**
   * Returns an HTML text with highlighted text-parts that match the query.
   *
   * @return string|null
   *   If set, an HTML text containing highlighted portions of the fulltext that
   *   match the query. NULL otherwise.
   */
  public function getExcerpt();

  /**
   * Sets an HTML text with highlighted text-parts that match the query.
   *
   * @param string $excerpt
   *   The HTML text with highlighted text-parts that match the query.
   *
   * @return self
   *   The invoked object.
   */
  public function setExcerpt($excerpt);

  /**
   * Determines whether extra data with a specific key is set on this item.
   *
   * @param string $key
   *   The extra data's key.
   *
   * @return bool
   *   TRUE if the data is set, FALSE otherwise.
   */
  public function hasExtraData($key);

  /**
   * Retrieves extra data for this item.
   *
   * @param string $key
   *   The key of the extra data. The following keys are used in the Search API
   *   project itself:
   *   - highlighted_fields: A sub-array of the item's fields, with their field
   *     data highlighted for display to the user. Only used for search results.
   *   However, contrib modules can define arbitrary other keys. (Usually they
   *   should be prefixed with the module name, though.)
   * @param mixed $default
   *   (optional) The value to return if the data is not set.
   *
   * @return mixed
   *   The data set for that key, or $default if the data is not present.
   */
  public function getExtraData($key, $default = NULL);

  /**
   * Retrieves all extra data set for this item.
   *
   * @return array
   *   An array mapping extra data keys to their data.
   */
  public function getAllExtraData();

  /**
   * Sets some extra data for this item.
   *
   * @param string $key
   *   The key for the extra data.
   * @param mixed $data
   *   (optional) The data to set. If NULL, remove the extra data with the given
   *   key instead.
   *
   * @return self
   *   The invoked object.
   */
  public function setExtraData($key, $data = NULL);

}
