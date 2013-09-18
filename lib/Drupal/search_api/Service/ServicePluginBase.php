<?php
/**
 * @file
 * Contains \Drupal\search_api\Service\ServicePluginBase.
 */

namespace Drupal\search_api\Service;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\PluginBase;

/**
 * Abstract class with generic implementation of most service methods.
 *
 * For creating your own service class extending this class, you only need to
 * implement indexItems(), deleteItems() and search() from the
 * ServiceInterface interface.
 */
abstract class ServicePluginBase extends PluginBase implements ServiceInterface {

  // @todo: Implement the default plugin base.

}
