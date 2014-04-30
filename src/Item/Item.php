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
 * Represents a search api indexing or result item.
 *
 * @TODO Remove ArrayAccess - used for backward compatibility while porting.
 */
class Item implements ItemInterface, \ArrayAccess {

  /**
   * The complex data item this search api item bases on.
   *
   * @var \Drupal\Core\TypedData\ComplexDataInterface
   */
  protected $source;

  /**
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The extracted fields to index.
   * @var array
   */
  protected $fields;

  /**
   * The keys of the extracted fields to index.
   *
   * This is used to implement the iterator.
   * @var array
   */
  protected $fieldKeys;

  /**
   * Defines the position of the iterator.
   * @var int
   */
  protected $position = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct(IndexInterface $index, DatasourceInterface $datasource, ComplexDataInterface $source, $id) {
    $this->source = $source;
    $this->index = $index;
    $this->datasource = $datasource;
    $this->id = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatasource() {
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
    // @todo from where do we get the id?
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function extractIndexingFields() {
    // If the fields weren't extracted yet extract and "cache" them now.
    if ($this->fields === NULL) {
      $fields = $this->getIndex()->getItemFields($this->getDatasource()->getPluginId());
      $this->fields = Utility::extractFields($this->source, $fields);
      $this->fieldKeys = array_keys($this->fields);
    }
    return $this->fields;
  }


  /**
   * {@inheritdoc}
   */
  public function next() {
    $this->position++;
  }

  /**
   * {@inheritdoc}
   */
  public function current() {
    return $this->fields[$this->fieldKeys[$this->position]];
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->fieldKeys[$this->position];
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return isset($this->fieldKeys[$this->position]);
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->extractIndexingFields();
    $this->position = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset) {
    if (!in_array($offset, array('#datasource', '#item', '#item_id'))) {
      $this->extractIndexingFields();
      return isset($this->fields[$offset]);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset) {
    switch ($offset) {
      case '#datasource':
        return $this->getDatasource()->getPluginId();

      case '#item':
        return $this->getSource();

      case '#item_id':
        return $this->getId();
    }
    $this->extractIndexingFields();
    return $this->fields[$offset];
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value) {
    $this->extractIndexingFields();
    if (isset($this->fields[$offset])) {
      if (isset($value['type'])) {
        $this->fields[$offset]->setType($value['type']);
      }
      if (isset($value['value'])) {
        $this->fields[$offset]->setValue($value['value']);
      }
      if (isset($value['original_type'])) {
        $this->fields[$offset]->setOriginalType($value['original_type']);
      }
    }
    else {
      $original_type = isset($value['original_type']) ? $value['original_type'] : NULL;
      $field_value = isset($value['value']) ? $value['value'] : array();
      $this->fields[$offset] = new Field($offset, $value['type'], $field_value, $original_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset) {
    $this->extractIndexingFields();
    unset($this->fields[$offset]);
  }
}
