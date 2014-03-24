<?php
/**
 * @file
 * Contains \Drupal\search_api\Queue\IndexQueueWorker.
 */

namespace Drupal\search_api\Queue;

use Drupal;

/**
 * Provides functionality to process the Search API index queue.
 */
final class IndexQueueWorker {

  /**
   * Queue worker callback for indexing some items.
   *
   * @param array $task
   *   An associative array containing:
   *   <ul>
   *     <li>index: The ID of the index on which items should be indexed.</li>
   *     <li>items: The items that should be indexed.</li>
   *   </ul>
   */
  public static function process(array $task) {
    // Get the index storage controller.
    $storage_controller = Drupal::entityManager()->getStorageController('search_api_index');
    // Try to load the search index.
    if (($index = $storage_controller->load($task['index']))) {
      // Check if the index is enabled, writable and has items to process.
      if ($index->status() && !$index->isReadOnly() && $task['items']) {
        // @todo: Process the items.
      }
    }
    else {
      // Log missing search index to the watchdog.
      watchdog('Search API', 'The search index @index is missing, cannot process queued item.', array('@index' => $task['index']), WATCHDOG_WARNING);
    }
  }

}
