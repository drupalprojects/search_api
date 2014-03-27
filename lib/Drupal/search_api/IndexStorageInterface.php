<?php

/**
 * @file
 * Definition of Drupal\search_api\IndexStorageInterface.
 */

namespace Drupal\search_api;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;


/**
 * Defines the controller class for indexes.
 *
 * This extends the Drupal\Core\Entity\ConfigStorageController class, adding
 * required special handling for index entities.
 */
interface IndexStorageInterface {

  /**
   * Gets the indexes that are configured for the given entity.
   *
   * @param ContentEntityInterface $entity
   *   The entity.
   * @return array
   *   Array of Index.
   */
  public function getIndexesForEntity(ContentEntityInterface $entity);
}