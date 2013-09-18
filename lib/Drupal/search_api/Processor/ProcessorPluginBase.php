<?php
/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorPluginBase.
 */

namespace Drupal\search_api\Processor;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\PluginBase;

/**
 * Defines a base processor implementation that most plugins will extend.
 *
 * Simple processors can just override process(), while others might want to
 * override the other process*() methods, and test*() (for restricting
 * processing to something other than all fulltext data).
 */
abstract class ProcessorPluginBase extends PluginBase implements ProcessorInterface {

  // @todo: Implement the default plugin base.

}
