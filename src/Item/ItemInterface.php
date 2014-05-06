<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\ItemInterface.
 */

namespace Drupal\search_api\Item;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * Represents a search api indexing or result item.
 */
interface ItemInterface extends \Iterator {

  /**
   * Create instance.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The search api index.
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource of this item.
   * @param \Drupal\Core\TypedData\ComplexDataInterface $source
   *   The source of this item.
   * @param string $id
   *   The id of this item.
   */
  public function __construct(IndexInterface $index, DatasourceInterface $datasource, ComplexDataInterface $source, $id);

  /**
   * Returns the item id.
   *
   * @return string
   *   The id of this item.
   */
  public function getId();

  /**
   * Returns the original complex data item this search api item bases on.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface
   *   The complex data item this search api item bases on.
   */
  public function getSource();

  /**
   * Get the datasource of this item.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface
   *   The datasource of this item
   */
  public function getDatasource();

  /**
   * Get the index of this item.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The index of this item
   */
  public function getIndex();

  /**
   * Return the item fields.
   *
   * @return \Drupal\search_api\Item\Field[]
   *   The array with the fields of this item.
   */
  public function extractIndexingFields();

//  /**
//   * Returns the score of the item.
//   *
//   * @return float
//   *   The score of the item.
//   */
//  public function getScore();
//
//  /**
//   * Set the boost value.
//   *
//   * @param float $boost
//   *   The boost value to set.
//   */
//  public function setBoost($boost);
//  /**
//   * Get the boost value.
//   *
//   * @return float
//   *   The boost value.
//   */
//  public function getBoost();

  /**
   * Return an HTML text with highlighted text-parts that match the query.
   *
   * @return string
   *   If set, an HTML text containing highlighted portions of the fulltext that
   *   match the query.
   */
  public function getExcerpt();

  /**
   * Set an HTML text with highlighted text-parts that match the query.
   *
   * @param string $excerpt
   *   The HTML text with highlighted text-parts that match the query.
   */
  public function setExcerpt($excerpt);

}
