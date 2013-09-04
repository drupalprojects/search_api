<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Type\Processor\ProcessorInterface.
 */

namespace Drupal\search_api\Plugin\Type\Processor;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\QueryInterface;

/**
 * Interface representing a Search API pre- and/or post-processor.
 *
 * While processors are enabled or disabled for both pre- and postprocessing at
 * once, many processors will only need to run in one of those two phases. Then,
 * the other method(s) should simply be left blank. A processor should make it
 * clear in its description or documentation when it will run and what effect it
 * will have.
 * Usually, processors preprocessing indexed items will likewise preprocess
 * search queries, so these two methods should mostly be implemented either both
 * or neither.
 */
interface ProcessorInterface extends ConfigurablePluginInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Constructs a processor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *   Contains the following keys:
   *   - index: The \Drupal\search_api\IndexInterface object this processor is
   *     attached to.
   *   - options: An array of settings entered by the user into the processor's
   *     configuration form.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition);

  /**
   * Checks whether this processor is applicable for a certain index.
   *
   * This can be used for hiding the processor on the index's "Workflow" tab. To
   * avoid confusion, you should only use criteria that are immutable, such as
   * the index's item type. Also, since this is only used for UI purposes, you
   * should not completely rely on this to ensure certain index configurations
   * and at least throw an exception with a descriptive error message if this is
   * violated on runtime.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check for.
   *
   * @return boolean
   *   TRUE if the processor can run on the given index; FALSE otherwise.
   */
  public static function supportsIndex(IndexInterface $index);

  /**
   * Declare the properties that are added to items by this processor.
   *
   * If one of the specified properties already exists for an entity it will be
   * overridden, so keep a clear namespace by prefixing the properties with the
   * module name if this is not desired.
   *
   * CAUTION: Since this method is used when calling
   * \Drupal\search_api\IndexInterface::getFields(), calling that method from
   * inside propertyInfo() will lead to a recursion and should therefore be
   * avoided.
   *
   * @see hook_entity_property_info()
   *
   * @return array
   *   Information about all additional properties.
   */
  public function propertyInfo();

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
   *   by ServiceInterface::indexItems().
   */
  public function preprocessIndexItems(array &$items);

  /**
   * Preprocess a search query.
   *
   * The same applies as when preprocessing indexed items: typically, only the
   * fulltext search keys should be processed, queries on specific fields should
   * usually not be altered.
   *
   * @param \Drupal\search_api\Plugin\search_api\QueryInterface $query
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
   *   An array containing the search results. See the return value of
   *   QueryInterface->execute() for the detailed format.
   * @param \Drupal\search_api\Plugin\search_api\QueryInterface $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query);

}
