<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\DatasourceItemInterface.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include the required classes and interfaces.
 */
use Drupal\Core\TypedData\IdentifiableInterface;
use Drupal\Core\TypedData\ComplexDataInterface;

/**
 * Interface which describes a datasource item.
 */
interface DatasourceItemInterface extends IdentifiableInterface, ComplexDataInterface { }
