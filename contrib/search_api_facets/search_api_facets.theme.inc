<?php
// $Id$

/**
 * Theme a list of facet links.
 *
 * @param array $variables
 *   An associative array containing:
 *   - terms: An array of available facet terms, as render arrays (or HTML).
 *   - active: An array of active facet filter, as render arrays (or HTML).
 */
function theme_search_api_facets_list(array $variables) {
  $items = array();
  foreach ($variables['active'] as $filter) {
    $items[] = render($filter);
  }
  foreach ($variables['terms'] as $term) {
    $items[] = render($term);
  }
  return $items ? theme('item_list', array('items' => $items, 'class' => array('search-api-facets'))) : '';
}

/**
 * Theme a single facet item.
 *
 * @param array $variables
 *   An associative array containing:
 *   - name: The name to display for the item.
 *   - count: The number of results for a non-active facet.
 *   - path: The destination path when the facet link is clicked.
 *   - options: An array of options to pass to l().
 *   - active: A boolean telling whether this facet filter is active or not.
 */
function theme_search_api_facets_facet(array $variables) {
  $name = $variables['name'];
  $path = $variables['path'];
  $options = $variables['options'];

  if ($variables['active']) {
    return l('(-)', $path, $options) . ' ' . check_plain($name);
  }
  return l($name, $path, $options);
}
