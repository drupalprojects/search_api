<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourcePluginBase.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include required classes and interfaces.
 */
use Drupal\search_api\Plugin\ConfigurablePluginBase;

/**
 * Abstract base class for search datasource plugins.
 */
abstract class DatasourcePluginBase extends ConfigurablePluginBase implements DatasourceInterface { }
