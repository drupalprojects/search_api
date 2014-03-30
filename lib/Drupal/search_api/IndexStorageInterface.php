<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexStorageInterface.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\ImportableEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;


/**
 * Provides an interface for index entity storage.
 */
interface IndexStorageInterface extends ConfigEntityStorageInterface, ImportableEntityStorageInterface {

  /**
   * Gets the indexes that are configured for the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which indexes should be found.
   *
   * @return \Drupal\search_api\Index\IndexInterface[]
   *   The indexes indexing items of the type of this entity.
   */
  public function getIndexesForEntity(ContentEntityInterface $entity);

}
