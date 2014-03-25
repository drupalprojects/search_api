<?php
/**
 * @file
 * Contains \Drupal\search_api\Handler\ServerAccessHandler.
 */

namespace Drupal\search_api\Handler;

use Drupal\Core\Entity\EntityAccessController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an access controller for the Server entity.
 * @todo This should be subclassed from EntityAccessHandler
 * once https://drupal.org/node/2154435 has been committed
 */
class ServerAccessHandler extends EntityAccessController {

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
