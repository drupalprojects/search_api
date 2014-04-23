<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginBase.
 */

namespace Drupal\search_api\Datasource;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Plugin\IndexPluginBase;

/**
 * Defines a base class from which other datasources may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_datasource_info_alter(). The definition includes the
 * following keys:
 * - id: The unique, system-wide identifier of the datasource.
 * - label: The human-readable name of the datasource, translated.
 * - description: A human-readable description for the datasource, translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiDatasource(
 *   id = "my_datasource",
 *   label = @Translation("My item type"),
 *   description = @Translation("Exposes my custom items as an item type."),
 * )
 * @endcode
 */
abstract class DatasourcePluginBase extends IndexPluginBase implements DatasourceInterface {

  /**
   * Retrieves the index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   An instance of IndexInterface.
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL) {
    $buildList = $this->viewMultipleItems(array($item), $view_mode, $langcode);
    return $buildList[0];
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL) {
    return array_fill_keys(array_keys($items), array());
  }

  /**
   * {@inheritdoc}
   */
  public function startTracking() {
    // Implement basic tracking?
  }

  /**
   * {@inheritdoc}
   */
  public function stopTracking() {
    // Implement basic tracking?
  }
}
