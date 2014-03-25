<?php
/**
 * @file
 * Contains \Drupal\search_api\Handler\IndexAccessHandler.
 */

namespace Drupal\search_api\Handler;

use Drupal\Core\Entity\EntityAccessController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access controller for the Index entity.
 * @todo - will need to subclass EntityAccessHandler once 
 * https://drupal.org/node/2154435 is in core
 */
class IndexAccessHandler extends EntityAccessController {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, $langcode, AccountInterface $account) {
    return $account->hasPermission('administer search_api');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return $account->hasPermission('administer search_api');
  }

}
