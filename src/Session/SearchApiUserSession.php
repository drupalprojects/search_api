<?php

/**
 * @file
 * Contains \Drupal\search_api\Session\SearchApiUserSession.
 */

namespace Drupal\search_api\Session;

use Drupal\Core\Session\AnonymousUserSession;

/**
 * Provides a user session with easy-to-set roles.
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\RenderedItem::preprocessIndexItems()
 */
class SearchApiUserSession extends AnonymousUserSession {

  /**
   * Constructs a SearchApiUserSession object.
   *
   * Allows a "roles" parameter to be passed in, as opposed to
   * \Drupal\Core\Session\AnonymousUserSession which doesn't allow any
   * parameters.
   *
   * @param array $roles
   *   (optional) An array of user roles (e.g. 'anonymous', 'authenticated').
   */
  public function __construct(array $roles = array()) {
    parent::__construct();

    if ($roles) {
      $this->roles = $roles;
    }
  }

}
