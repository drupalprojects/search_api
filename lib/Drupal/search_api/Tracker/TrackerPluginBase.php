<?php

/**
 * @file
 * Contains \Drupal\search_api\Tracker\TrackerPluginBase.
 */

namespace Drupal\search_api\Tracker;

use Drupal\search_api\Plugin\IndexPluginBase;
use Drupal\search_api\Tracker\TrackerInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Exception\SearchApiException;

/**
 * Defines a base class from which other tracker classes may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition array may be altered through
 * hook_search_api_tracker_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the tracker class.
 * - label: The human-readable name of the tracker class, translated.
 * - description: A human-readable description for the tracker class,
 *   translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiTracker(
 *   id = "my_tracker",
 *   label = @Translation("My tracker"),
 *   description = @Translation("Simple tracking system.")
 * )
 * @endcode
 */
abstract class TrackerPluginBase extends IndexPluginBase implements TrackerInterface {

  /**
   * Validate whether a datasource is attached to our index.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   An instance of DatesourceInterface which needs to be validated.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   Thrown when the datasource is not attached to the index.
   */
  protected function validateDatasource(DatasourceInterface $datasource) {
    // Validate whether the datasource is owned by the index.
    if (!$this->getIndex()->id() !== $datasource->getIndex()->id()) {
      throw new SearchApiException('Cannot track datatsource due to index mismatch');
    }
  }

}
