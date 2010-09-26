<?php
// $Id$

/**
 * Displays a form for selecting the indexed fields for which facets should be
 * created.
 *
 * @param SearchApiIndex $index
 *   The index for which to display the settings.
 */
function search_api_facets_index_select(array $form, array &$form_state, SearchApiIndex $index) {
  if (!$index->server() || !$index->server()->supportsFeature('facets')) {
    // We can't offer any facets, but maybe the user can delete old facet data.
    drupal_set_message(t("The server this index currently lies on doesn't support facets. " .
        'To use facets, you will have to move this index to a server supporting this feature.'), 'error');
    if (db_query('SELECT COUNT(1) FROM {search_api_facet} WHERE index_id = :id', array(':id' => $index->id))->fetchCol()) {
      $form['index'] = array(
        '#type' => 'value',
        '#value' => $index->id,
      );
      $form['delete'] = array(
        '#type' => 'submit',
        '#title' => t('Delete facet settings'),
        '#description' => t("If you won't use facets with this index anymore, you can delete all facet data associated with it. " .
            "This will delete all settings for facets on this index. Facets on other indexes won't be influenced."),
        '#value' => t('Delete facet settings'),
        '#submit' => array('search_api_facets_index_select_submit_delete'),
      );
    }
    return $form;
  }


}

/**
 * Submit callback for search_api_facets_index_select().
 */
function search_api_facets_index_select_submit(array $form, array &$form_state) {

}

/**
 * Submit callback for search_api_facets_index_select(), when clicking on
 * "Delete facet settings".
 */
function search_api_facets_index_select_submit_delete(array $form, array &$form_state) {
  $index_id = $form_state['values']['index'];
  $ret = search_api_facet_delete(array('index_id' => $index_id));
  if ($ret) {
    drupal_set_message(t('All facet data stored for this index was deleted.'));
  }
  else {
    drupal_set_message(t('There was no data to delete.'), 'warning');
  }
  $form_state['redirect'] = 'admin/config/search/search_api/index/' . $index_id;
}

/**
 * Theming function for rendering a form as a table.
 *
 * @param array $variables
 *   An array of variables to use, containing only one entry:
 *   - element: The sub-form to render as a table.
 *
 * @return string
 *   HTML displaying the specified sub-form as a table.
 */
function theme_search_api_facets_form_table(array $variables) {
  $form = $variables['element'];

  $rows = array();
  foreach (element_children($form) as $id) {
    $row = array();
    foreach (element_children($form[$id]) as $field) {
      if ($cell = render($form[$id][$field])) {
        $row[] = $cell;
      }
    }
    $rows[] = $row;
  }

  $vars['rows'] = $rows;
  if (isset($form['#table_header'])) {
    $vars['header'] = $form['#table_header'];
  }
  if (isset($form['#table_empty'])) {
    $vars['empty'] = $form['#table_empty'];
  }
  return theme('table', $vars);
}
