<?php
/**
 * @file
 * Contains \Drupal\search_api\Procesor\ProcessorInterface.
 */

namespace Drupal\search_api\Processor;

/*
 * Include required classes and interfaces.
 */
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface representing a Search API pre- and/or post-processor.
 *
 * While processors are enabled or disabled for both pre- and postprocessing at
 * once, many processors will only need to run in one of those two phases. Then,
 * the other method(s) should simply be left blank. A processor should make it
 * clear in its description or documentation when it will run and what effect it
 * will have.
 *
 * Usually, processors preprocessing indexed items will likewise preprocess
 * search queries, so these two methods should mostly be implemented either both
 * or neither.
 */
interface ProcessorInterface extends ConfigurablePluginInterface, PluginInspectionInterface, PluginFormInterface {

  // @todo: Add additional functionality.

}
