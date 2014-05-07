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
 */
class Item implements ItemInterface {

  /**
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

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
   * The extracted fields to index.
   *
   * @var \Drupal\search_api\Item\FieldInterface[]
   */
  protected $fields;

  /**
   * The keys of the extracted fields to index.
   *
   * This is used to implement the iterator.
   *
   * @var array
   */
  protected $fieldKeys;

  /**
   * Defines the position of the iterator.
   *
   * @var int
   */
  private $position = 0;

  /**
   * The HTML text with highlighted text-parts that match the query.
   *
   * @var string
   */
  protected $excerpt;

  /**
   * Constructs a new Item object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The search api index.
   * @param \Drupal\Core\TypedData\ComplexDataInterface $source
   *   The source of this item.
   * @param string $id
   *   The ID of this item.
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource of this item.
   */
  public function __construct(IndexInterface $index, ComplexDataInterface $source, $id, DatasourceInterface $datasource = NULL) {
    $this->index = $index;
    $this->source = $source;
    $this->id = $id;
    $this->datasource = $datasource;
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
  public function getExcerpt() {
    return $this->excerpt;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcerpt($excerpt) {
    $this->excerpt = $excerpt;
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

}
