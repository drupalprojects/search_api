<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\TranslatableDatasourceItemInterface.
 */

namespace Drupal\search_api\Datasource;

/*
 * Include the required classes and interfaces.
 */
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Interface which describes a translatable datasource item.
 */
interface TranslatableDatasourceItemInterface extends DatasourceItemInterface, TranslatableInterface { }
