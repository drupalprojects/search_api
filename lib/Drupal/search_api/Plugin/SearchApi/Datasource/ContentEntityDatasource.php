<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasource.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\Datasource;
use Drupal\search_api\Datasource\DatasourcePluginBase;

/**
 * Represents a datasource which exposes the content entities.
 *
 * @Datasource(
 *   id = "search_api_content_entity_datasource",
 *   label = @Translation("Content entity datasource"),
 *   desciption = @Translation("Exposes the content entities as datasource."),
 *   derivative = "Drupal\search_api\Datasource\Entity\ContentEntityDatasourceDerivative"
 * )
 */
class ContentEntityDatasource extends DatasourcePluginBase {

  // @todo: Needs additional functionality.

}
