<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\IndexPluginBase.
 */

namespace Drupal\search_api\Plugin;

/**
 * Base class for plugins that are associated with a certain index.
 */
class IndexPluginBase extends ConfigurablePluginBase implements IndexPluginInterface {

  /**
   * The index this processor is configured for.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    if (!empty($configuration['index'])) {
      $index = $container
        ->get('entity.manager')
        ->getStorageController('search_api_index')
        ->load($configuration['index']);
      $processor->setIndex($index);
    }
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function setIndex(IndexInterface $index) {
    $this->index = $index;
  }

}
