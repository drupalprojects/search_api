<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\ProcessorInterface.
 */

namespace Drupal\search_api\Plugin\search_api;

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
interface ProcessorInterface {

  /**
   * Construct a processor.
   *
   * @param Index $index
   *   The index for which processing is done.
   * @param array $options
   *   The processor options set for this index.
   */
  public function __construct(Index $index, array $options = array());

  /**
   * Check whether this processor is applicable for a certain index.
   *
   * This can be used for hiding the processor on the index's "Workflow" tab. To
   * avoid confusion, you should only use criteria that are immutable, such as
   * the index's item type. Also, since this is only used for UI purposes, you
   * should not completely rely on this to ensure certain index configurations
   * and at least throw an exception with a descriptive error message if this is
   * violated on runtime.
   *
   * @param Index $index
   *   The index to check for.
   *
   * @return boolean
   *   TRUE if the processor can run on the given index; FALSE otherwise.
   */
  public function supportsIndex(Index $index);

  /**
   * Display a form for configuring this processor.
   * Since forcing users to specify options for disabled processors makes no
   * sense, none of the form elements should have the '#required' attribute set.
   *
   * @return array
   *   A form array for configuring this processor, or FALSE if no configuration
   *   is possible.
   */
  public function configurationForm();

  /**
   * Validation callback for the form returned by configurationForm().
   *
   * @param array $form
   *   The form returned by configurationForm().
   * @param array $values
   *   The part of the $form_state['values'] array corresponding to this form.
   * @param array $form_state
   *   The complete form state.
   */
  public function configurationFormValidate(array $form, array &$values, array &$form_state);

  /**
   * Submit callback for the form returned by configurationForm().
   *
   * This method should both return the new options and set them internally.
   *
   * @param array $form
   *   The form returned by configurationForm().
   * @param array $values
   *   The part of the $form_state['values'] array corresponding to this form.
   * @param array $form_state
   *   The complete form state.
   *
   * @return array
   *   The new options array for this callback.
   */
  public function configurationFormSubmit(array $form, array &$values, array &$form_state);

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
   * @param DefaultQuery $query
   *   The object representing the query to be executed.
   */
  public function preprocessSearchQuery(DefaultQuery $query);

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
   * @param DefaultQuery $query
   *   The object representing the executed query.
   */
  public function postprocessSearchResults(array &$response, DefaultQuery $query);

}
