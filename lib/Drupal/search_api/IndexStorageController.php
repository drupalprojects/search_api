<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexStorageController.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigStorageController;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the storage controller class for Index entities.
 */
class IndexStorageController extends ConfigStorageController {

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    $entities = parent::loadMultiple($ids);
    // Only blocks with a valid plugin should be loaded.
    return array_filter($entities, function ($entity) {
      return $entity->getPlugin();
    });
  }

}
