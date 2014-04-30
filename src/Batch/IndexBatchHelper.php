<?php

/**
 * @file
 * Contains \Drupal\search_api\Batch\IndexBatchHelper.
 */

namespace Drupal\search_api\Batch;

use Drupal\search_api\Index\IndexInterface;

/**
 * Helper class for indexing items using the batch API.
 */
class IndexBatchHelper {

  /**
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected static $translationManager;

  /**
   * Gets the translation manager.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface
   *   The translation manager.
   */
  protected static function translationManager() {
    if (!static::$translationManager) {
      static::$translationManager = \Drupal::service('string_translation');
    }
    return static::$translationManager;
  }

  /**
   * Translates a string to the current language or to a given language.
   *
   * See the t() documentation for details.
   */
  protected static function t($string, array $args = array(), array $options = array()) {
    return static::translationManager()->translate($string, $args, $options);
  }

  /**
   * Formats a string containing a count of items.
   *
   * See the format_plural() documentation for details.
   */
  protected static function formatPlural($count, $singular, $plural, array $args = array(), array $options = array()) {
    return static::translationManager()->formatPlural($count, $singular, $plural, $args, $options);
  }

  /**
   * Create a batch for a given search index.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * @param int $size
   *   Optional. Number of items to index per batch. Defaults to the cron limit
   *   set by the index.
   * @param int $limit
   *   Optional. Maximum number of items to index. Defaults to indexing all
   *   remaining items.
   */
  public static function create(IndexInterface $index, $size = NULL, $limit = -1) {
    // Check if the size should be determined by the index cron limit option.
    if ($size === NULL) {
      // Use the size set by the index.
      $size = $index->getOption('cron_limit', \Drupal::configFactory()->get('search_api.settings')->get('cron_limit'));
    }
    // Check if indexing items is allowed.
    if ($index->status() && !$index->isReadOnly() && $size !== 0 && $limit !== 0) {
      // Define the search index batch definition.
      $batch_definition = array(
        'operations' => array(
          array(array(__CLASS__, 'process'), array($index, $size, $limit)),
        ),
        'finished' => array(__CLASS__, 'finish'),
        'progress_message' => static::t('Completed about @percentage% of the indexing operation.'),
      );
      // Schedule the batch.
      batch_set($batch_definition);

      return TRUE;
    }
    return FALSE;
  }

  /**
   * Process an index batch operation.
   */
  public static function process(IndexInterface $index, $size, $limit, array &$context) {
    // Check if the sandbox should be initialized.
    if (!isset($context['sandbox']['limit'])) {
      // Initialize the sandbox with data which is shared among the batch runs.
      $context['sandbox']['limit'] = $limit;
      $context['sandbox']['batch_size'] = $size;
      $context['sandbox']['progress'] = 0;
    }
    // Check if the results should be initialized.
    if (!isset($context['results']['indexed'])) {
      // Initialize the results with data which is shared among the batch runs.
      $context['results']['indexed'] = 0;
      $context['results']['not indexed'] = 0;
    }
    // Get the remaining item count. When no valid tracker is available then
    // the value will be set to zero which will cause the batch process to
    // stop.
    $remaining_item_count = ($index->hasValidTracker() ? $index->getTracker()->getRemainingItemsCount() : 0);
    // Check if an explicit limit needs to be used.
    if ($context['sandbox']['limit'] > -1) {
      // Calculate the remaining amount of items that can be indexed. Note that
      // a minimum is taking between the allowed number of items and the
      // remaining item count to prevent incorrect reporting of not indexed
      // items.
      $actual_limit = min($context['sandbox']['limit'] - $context['sandbox']['progress'], $remaining_item_count);
    }
    else {
      // Use the remaining item count as actual limit.
      $actual_limit = $remaining_item_count;
    }
    // Determine the number of items to index for this run.
    $to_index = min($actual_limit, $context['sandbox']['batch_size']);
    // Catch any exception that may occur during indexing.
    try {
      // Index items limited by the given count.
      $indexed = $index->index($to_index);
      // Increment the indexed result and progress.
      $context['results']['indexed'] += $indexed;
      $context['results']['not indexed'] += ($to_index - $indexed);
      $context['sandbox']['progress'] += $to_index;
      // Display progress message.
      if ($indexed > 0) {
        $context['message'] = static::formatPlural($context['results']['indexed'], 'Successfully indexed 1 item.', 'Successfully indexed @count items.');
      }
      // Everything has been indexed?
      if ($indexed === 0 || $context['sandbox']['progress'] >= $context['sandbox']['limit']) {
        $context['finished'] = 1;
      }
      else {
        $context['finished'] = ($context['sandbox']['progress'] / $context['sandbox']['limit']);
      }
    }
    catch (\Exception $ex) {
      // Log exception to watchdog and abort the batch job.
      watchdog_exception('search_api', $ex);
      $context['message'] = static::t('An error occurred during indexing: @message', array('@message' => $ex->getMessage()));
      $context['finished'] = 1;
      $context['results']['not indexed'] += ($context['sandbox']['limit'] - $context['sandbox']['progress']);
    }
  }

  /**
   * Finish an index batch.
   */
  public static function finish($success, $results, $operations) {
    // Check if the batch job was successful.
    if ($success) {
      // Display the number of items indexed.
      if (!empty($results['indexed'])) {
        // Buid the indexed message.
        $indexed_message = static::formatPlural($results['indexed'], 'Successfully indexed 1 item.', 'Successfully indexed @count items.');
        // Notify user about indexed items.
        drupal_set_message($indexed_message);
        // Display the number of items not indexed.
        if (!empty($results['not indexed'])) {
          // Build the not indexed message.
          $not_indexed_message = static::formatPlural($results['not indexed'], '1 item could not be indexed. Check the logs for details.', '@count items could not be indexed. Check the logs for details.');
          // Notify user about not indexed items.
          drupal_set_message($not_indexed_message, 'warning');
        }
      }
      else {
        // Notify user about failure to index items.
        drupal_set_message(static::t('Couldn\'t index items. Check the logs for details.'), 'error');
      }
    }
    else {
      // Notify user about batch job failure.
      drupal_set_message(static::t('An error occurred while trying to index items. Check the logs for details.'), 'error');
    }
  }

}
