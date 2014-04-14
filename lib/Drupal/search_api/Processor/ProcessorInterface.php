<?php
/**
 * @file
 * Contains \Drupal\search_api\Processor\ProcessorInterface.
 */

namespace Drupal\search_api\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\IndexPluginInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Represents a Search API pre- and/or post-processor.
 *
 * While processors are enabled or disabled for both pre- and postprocessing at
 * once, many processors will only need to run in one of those two phases. Then,
 * the other method(s) should simply be left blank. A processor should make it
 * clear in its description or documentation when it will run and what effect it
 * will have.
 *
 * Usually, processors preprocessing indexed items will likewise preprocess
 * search queries, so these two methods should mostly be implemented either both
 * or neither.
 */
interface ProcessorInterface extends IndexPluginInterface {

  /**
   * Checks whether this processor is applicable for a certain index.
   *
   * This can be used for hiding the processor on the index's "Filters" tab. To
   * avoid confusion, you should only use criteria that are immutable, such as
   * the index's item type. Also, since this is only used for UI purposes, you
   * should not completely rely on this to ensure certain index configurations
   * and at least throw an exception with a descriptive error message if this is
   * violated on runtime.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index to check for.
   *
   * @return bool
   *   TRUE if the processor can run on the given index; FALSE otherwise.
   */
  public static function supportsIndex(IndexInterface $index);

  /**
   * Alters the property definitions for this index's item type.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $properties
   *   An array of property definitions for this item type.
   */
  public function alterPropertyDefinitions(array &$properties);

  /**
   * Preprocess data items for indexing.
   *
   * Typically, a preprocessor will execute its preprocessing (e.g. stemming,
   * n-grams, word splitting, stripping stop words, etc.) only on the items'
   * search_api_fulltext fields, if set. Other fields should usually be left
   * untouched.
   *
   * @param array $items
   *   An array of items to be preprocessed for indexing, formatted as specified
   *   by \Drupal\search_api\Service\ServiceSpecificInterface::indexItems().
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(QueryInterface $query);

  /**
   * Postprocess search results before display.
   *
   * If a class is used for both pre- and post-processing a search query, the
   * same object will be used for both calls (so preserving some data or state
   * locally is possible).
   *
   * @param array $response
   *   An array containing the search results, passed by reference. See the
   *   return value of \Drupal\search_api\Query\QueryInterface::execute() for
   *   the detailed format.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query);

}
