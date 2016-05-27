<?php

namespace Drupal\search_api_test\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a processor with dependencies, for the dependency removal tests.
 *
 * @SearchApiProcessor(
 *   id = "search_api_test",
 *   label = @Translation("Dependency test processor"),
 * )
 */
class TestProcessor extends ProcessorPluginBase {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      $this->configuration = array();
    }
    return $remove;
  }

}
