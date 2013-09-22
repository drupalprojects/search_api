<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Item\ItemInterface.
 */

namespace Drupal\search_api\Datasource\Item;

/*
 * Include the required classes and interfaces.
 */
use Drupal\Core\TypedData\IdentifiableInterface;
use Drupal\Core\TypedData\ComplexDataInterface;

/**
 * Interface which describes a datasource item.
 */
interface ItemInterface extends IdentifiableInterface, ComplexDataInterface { }
