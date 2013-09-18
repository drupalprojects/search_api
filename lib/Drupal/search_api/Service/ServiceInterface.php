<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServiceInterface.
 */

namespace Drupal\search_api\Service;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface defining the methods search services have to implement.
 *
 * Before a service object is used, the corresponding server's data will be read
 * from the database (see ServicePluginBase for a list of fields).
 */
interface ServiceInterface extends ConfigurablePluginInterface, PluginInspectionInterface, PluginFormInterface {

  // @todo: Add additional functionality.

}
