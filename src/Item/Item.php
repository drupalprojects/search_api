<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\Item.
 */

namespace Drupal\search_api\Item;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * Represents a Search API indexing or result item.
 */
class Item implements ItemInterface, \IteratorAggregate {

  /**
   * The search index with which this item is associated.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The complex data item this Search API item is based on.
   *
   * @var \Drupal\Core\TypedData\ComplexDataInterface
   */
  protected $originalObject;

  /**
   * The datasource of this item.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * The extracted fields of this item.
   *
   * @var \Drupal\search_api\Item\FieldInterface[]
   */
  protected $fields;

  /**
   * The HTML text with highlighted text-parts that match the query.
   *
   * @var string
   */
  protected $excerpt;

  /**
   * The score this item had as a result in a corresponding search query.
   *
   * @var float
   */
  protected $score = 1.0;

  /**
   * The boost of this item at indexing time.
   *
   * @var float
   */
  protected $boost = 1.0;

  /**
   * Extra data set on this item.
   *
   * @var array
   */
  protected $extraData = array();

  /**
   * Constructs a new Item object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The item's search index.
   * @param string $id
   *   The ID of this item.
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource of this item. If not set, it will be determined
   *   from the ID and loaded from the index.
   */
  public function __construct(IndexInterface $index, $id, DatasourceInterface $datasource = NULL) {
    $this->index = $index;
    $this->id = $id;
    $this->datasource = $datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource() {
    if (!isset($this->datasource)) {
      list($datasource_id) = Utility::splitCombinedId($this->id);
      $this->datasource = $this->index->getDatasource($datasource_id);
    }
    return $this->datasource;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalObject($load = TRUE) {
    if (!isset($this->originalObject) && $load) {
      $this->originalObject = $this->index->loadItem($this->id);
    }
    return $this->originalObject;
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalObject(ComplexDataInterface $original_object) {
    $this->originalObject = $original_object;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getField($field_id, $extract = FALSE) {
    $fields = $this->getFields($extract);
    return isset($fields[$field_id]) ? $fields[$field_id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($extract = FALSE) {
    if (!isset($this->fields) && $extract) {
      $fields = $this->getIndex()->getItemFields($this->getDatasource()->getPluginId());
      $this->fields = Utility::extractFields($this->originalObject, $fields);
    }
    return $this->fields ?: array();
  }

  /**
   * {@inheritdoc}
   */
  public function setField($field_id, FieldInterface $field = NULL) {
    // @todo Implement setField() method.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields(array $fields) {
    // @todo Implement setFields() method.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore() {
    // @todo Implement getScore() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setScore($score) {
    // @todo Implement setScore() method.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoost() {
    // @todo Implement getBoost() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setBoost($boost) {
    // @todo Implement setBoost() method.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExcerpt() {
    return $this->excerpt;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcerpt($excerpt) {
    $this->excerpt = $excerpt;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraData($key) {
    // @todo Implement hasExtraData() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getExtraData($key, $default = NULL) {
    // @todo Implement getExtraData() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getAllExtraData() {
    // @todo Implement getAllExtraData() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setExtraData($key, $data = NULL) {
    // @todo Implement setExtraData() method.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    // @todo Implement getIterator() method.
  }

  /**
   * Implements the magic __clone() method to implement a deep clone.
   */
  public function __clone() {

  }

}
