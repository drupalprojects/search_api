<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Item\TranslatableItemInterface.
 */

namespace Drupal\search_api\Datasource\Item;

/*
 * Include the required classes and interfaces.
 */
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Interface which describes a translatable datasource item.
 */
interface TranslatableItemInterface extends ItemInterface, TranslatableInterface { }
