<?php
// $Id$

/**
 * Theme a list of facet links.
 *
 * @param array $variables
 *   An associative array containing:
 *   - terms: An array of available facet terms, as render arrays (or HTML).
 *   - active: An array of active facet filters, as render arrays (or HTML).
 */
function theme_search_api_facets_list(array $variables) {
  $items = array();
  foreach ($variables['active'] as $filter) {
    $items[] = render($filter);
  }
  foreach ($variables['terms'] as $term) {
    $items[] = render($term);
  }
  return $items ? theme('item_list', array('items' => $items, 'attributes' => array('class' => array('search-api-facets')))) : '';
}

/**
 * Theme a single facet item.
 *
 * @param array $variables
 *   An associative array containing:
 *   - name: The name to display for the item.
 *   - field: The name of the facetted field (active facets only).
 *   - count: The number of results for a non-active facet.
 *   - path: The destination path when the facet link is clicked.
 *   - options: An array of options to pass to l().
 *   - active: A boolean telling whether this facet filter is active or not.
 */
function theme_search_api_facets_facet(array $variables) {
  $name = $variables['name'];
  $path = $variables['path'];
  $options = $variables['options'] + array('attributes' => array());
  $options['attributes'] += array('class' => array());

  if ($variables['active']) {
    $options['attributes']['class'][] = 'search-api-facet-remove';
    $field = '';
    if ($variables['field']) {
      $field = ' <span class="search-api-facet-field">' . $variables['field'] . ':</span>';
    }
    return l('(-)', $path, $options) . $field . ' <span class="search-api-facet-value">' . check_plain($name) . '</span>';
  }
  $options['attributes']['class'][] = 'search-api-facet-value';
  return l($name, $path, $options) . ' <span class="search-api-facet-count">(' . $variables['count'] . ')</span>';
}

/**
 * Theme one item of the "Current search" block.
 *
 * @param array $variables
 *   An associative array containing:
 *   - index: The SearchApiIndex the search was executed on.
 *   - keys: The fulltext keys used.
 *   - count: The number of results found.
 *   - path: The destination path when the facet link is clicked.
 *   - options: An array of options to pass to l().
 *   - filters: An array of active facet filters for this search, as render
 *     arrays (or HTML).
 */
function theme_search_api_facets_current_search(array $variables) {
  $items = array();
  foreach ($variables['filters'] as $filter) {
    $items[] = render($filter);
  }
  $items = $items ? theme('item_list', array('items' => $items, 'attributes' => array('class' => array('search-api-facets')))) : '';
  $link = l($variables['keys'], $variables['path'], $variables['options']);
  $results = format_plural($variables['count'], '1 result', '@count results');
  return '<h3>' . $link . ' (' . $results . ")</h3>\n" . $items;
}
