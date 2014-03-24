<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Item\ItemStates.
 */

namespace Drupal\search_api\Datasource\Item;

/**
 * Defines the states of an item.
 */
final class ItemStates {

  /**
   * Indicates the latest version of an item is present in the index.
   */
  const INDEXED = 0;

  /**
   * Indicates a deprecated version of an item is present in the index and
   * needs to be updated.
   */
  const CHANGED = 1;

  /**
   * Indicates an item has been scheduled to be processed during cron.
   */
  const QUEUED = 2; // @todo: Determine whether this should be removed?

}
