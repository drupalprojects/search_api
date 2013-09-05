<?php
/**
 * @file
 * Contains Drupal\search_api\ServerInterface.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\search_api\Plugin\Type\Service\ServiceInterface;

/**
 * Defines the interface for server entities.
 */
interface ServerInterface extends ConfigEntityInterface, ServiceInterface {

}
