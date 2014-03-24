<?php
/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorInterface.
 */

namespace Drupal\search_api\Processor;

use Drupal\search_api\Plugin\ConfigurablePluginInterface;

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
interface ProcessorInterface extends ConfigurablePluginInterface {

  // @todo: Add additional functionality.

}
