<?php
// $Id$

/**
 * Views query class using a Search API index as the data source.
 */
class SearchApiViewsQuery extends views_plugin_query {

  /**
   * Number of results to display.
   *
   * @var int
   */
  protected $limit;

  /**
   * Offset of first displayed result.
   *
   * @var int
   */
  protected $offset;

  /**
   * Constructor; Create the basic query object and fill with default values.
   */
  function init($base_table, $base_field, $options) {
    $this->options = $options;
  }

  /**
   * Generate a query and a countquery from all of the information supplied
   * to the object.
   *
   * @param $get_count
   *   Provide a countquery if this is true, otherwise provide a normal query.
   */
  function query($get_count = FALSE) {

  }

  /**
   * Let modules modify the query just prior to finalizing it.
   */
  function alter(&$view) {

  }

  /**
   * Builds the necessary info to execute the query.
   */
  function build(&$view) {

  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  function execute(&$view) {

  }

  /**
   * Add a signature to the query, if such a thing is feasible.
   *
   * This signature is something that can be used when perusing query logs to
   * discern where particular queries might be coming from.
   */
  function add_signature(&$view) {

  }

  /**
   * Get aggregation info for group by queries.
   *
   * If NULL, aggregation is not allowed.
   */
  function get_aggregation_info() {

  }

  /**
   * Add settings for the UI.
   */
  function options_form(&$form, &$form_state) {

  }

  function options_validate(&$form, &$form_state) {

  }

  function options_submit(&$form, &$form_state) {

  }

}
