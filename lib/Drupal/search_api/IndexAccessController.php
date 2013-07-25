<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexAccessController.
 */

namespace Drupal\search_api;

use Drupal\Core\Entity\EntityAccessController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an index access controller.
 */
class IndexAccessController extends EntityAccessController {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, $langcode, AccountInterface $account) {
    return $account->hasPermission('administer search_api');
  }

}
