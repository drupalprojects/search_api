<?php
/**
 * @file
 * Contains \Drupal\search_api\Index\IndexStatusInterface.
 */

namespace Drupal\search_api\Index;

/**
 * Interface which describes the status of an index.
 */
interface IndexStatusInterface {

  /**
   * Get the number of items already indexed.
   *
   * @return integer
   *   The number of items already indexed.
   */
  public function getIndexedCount();

  /**
   * Get the total number of items that have to be indexed.
   *
   * @return integer
   *   The total number of items that have to be indexed.
   */
  public function getTotalCount();

}
