<?php
// $Id$

/**
 * Displays a search page.
 *
 * @param $id
 *   The search page's ID.
 * @param $keys
 *   The keys to search for.
 */
function search_api_page_view($id, $keys = NULL) {
  $page = search_api_page_load($id);
  if (!$page) {
    return MENU_NOT_FOUND;
  }

  $ret['form'] = drupal_get_form('search_api_page_search_form', $page, $keys);

  if ($keys) {
    // @todo This is a fix for an assumed PDO bug which would produce a WSoD.
    new SearchApiIndex();
    $ret['results']['#theme'] = 'search_api_page_results';
    $ret['results']['#index'] = search_api_index_load($page->index_id);
    $ret['results']['#results'] = $results = search_api_page_search_execute($page, $keys);
    $ret['results']['#keys'] = $keys;

    if ($results['result count'] > $page->options['per_page']) {
      pager_default_initialize($results['result count'], $page->options['per_page']);
      $ret['pager']['#theme'] = 'pager';
      $ret['pager']['#quantity'] = (int) ceil($results['result count'] / $page->options['per_page']);
    }

    if (!empty($results['ignored'])) {
      drupal_set_message(t('The following search keys are too short or too common and were therefore ignored: "@list".', array('@list' => implode(t('", "'), $results['ignored']))), 'warning');
    }
    if (!empty($results['warnings'])) {
      foreach ($results['warnings'] as $warning) {
        drupal_set_message($warning, 'warning');
      }
    }
  }

  return $ret;
}

/**
 * Display a search form.
 *
 * @param stdClass $page
 *   The search page for which this form is displayed.
 * @param $keys
 *   The search keys.
 * @param $compact
 *   Whether to display a compact form (e.g. for blocks) instead of a normal one.
 */
function search_api_page_search_form(array $form, array &$form_state, stdClass $page, $keys = NULL, $compact = FALSE) {
  $form['keys'] = array(
    '#type' => 'textfield',
    '#title' => t('Enter your keywords'),
    '#default_value' => $keys,
  );
  $form['base'] = array(
    '#type' => 'value',
    '#value' => $page->path,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Search'),
  );

  if (!$compact) {
    $form = array(
      '#type' => 'fieldset',
      '#title' => $page->name,
      'form' => $form,
    );
    if ($page->description) {
      $form['text']['#markup'] = $page->description;
      $form['text']['#weight'] = -5;
    }
  }

  return $form;
}

/**
 * Validation callback for search_api_page_search_form().
 */
function search_api_page_search_form_validate(array $form, array &$form_state) {
  if (!trim($form_state['values']['keys'])) {
    form_set_error('keys', t('Please enter at least one keyword.'));
  }
}

/**
 * Submit callback for search_api_page_search_form().
 */
function search_api_page_search_form_submit(array $form, array &$form_state) {
  $keys = trim($form_state['values']['keys']);
  // @todo Take care of "/"s in the keys
  $form_state['redirect'] = $form_state['values']['base'] . '/' . $keys;
}

/**
 * Executes a search.
 *
 * @param stdClass $page
 *   The page for which a search should be executed.
 * @param $keys
 *   The keywords to search for.
 *
 * @return array
 *   The search results as returned by SearchApiQueryInterface::execute().
 */
function search_api_page_search_execute(stdClass $page, $keys) {
  $limit = $page->options['per_page'];
  $offset = pager_find_page() * $limit;
  return search_api_query($page->index_id)
    ->keys($keys)
    ->range($offset, $limit)
    ->execute();
}

/**
 * Theme function for displaying search results.
 *
 * @param array $variables
 *   An associative array containing:
 *   - index: The index this search was executed on.
 *   - results: An array of search results, as returned by
 *     SearchApiQueryInterface::execute().
 *   - keys: The keywords of the executed search.
 */
function theme_search_api_page_results(array $variables) {
  drupal_add_css(drupal_get_path('module', 'search_api_page') . '/search_api_page.css');

  $index = $variables['index'];
  $results = $variables['results'];
  $keys = $variables['keys'];

  $output = '<p class="search-performance">' . format_plural($results['result count'],
      'The search found 1 result in @sec seconds.',
      'The search found @count results in @sec seconds.',
      array('@sec' => round($results['performance']['complete'], 3))) . '</p>';

  if (!$results['result count']) {
    $output .= "\n<h2>" . t('Your search yielded no results') . "</h2>\n";
    return $output;
  }

  $output .= "\n<h2>" . t('Search results') . "</h2>\n";
  $output .= '<ol class="search-results">';
  foreach ($results['results'] as $item) {
    $output .= '<li class="search-result">' . theme('search_api_page_result', array('index' => $index, 'result' => $item, 'keys' => $keys)) . '</li>';
  }
  $output .= '</ol>';
  return $output;
}

/**
 * Theme function for displaying search results.
 *
 * @param array $variables
 *   An associative array containing:
 *   - index: The index this search was executed on.
 *   - result: One item of the search results, an array containing the keys
 *     'id', 'entity' and 'score'.
 *   - keys: The keywords of the executed search.
 */
function theme_search_api_page_result(array $variables) {
  $index = $variables['index'];
  $id = $variables['result']['id'];
  $entity = $variables['result']['entity'];
  entity_prepare_view($index->entity_type, array($entity));

  $wrapper = entity_metadata_wrapper($index->entity_type, $entity);

  $url = entity_uri($index->entity_type, $entity);
  $name = entity_label($index->entity_type, $entity);

  $fields = $index->options['fields'];
  foreach ($fields as $field => $info) {
    if (!$info['indexed'] || !search_api_is_text_type($info['type'])) {
      unset($fields[$field]);
    }
  }
  $fields = search_api_extract_fields($wrapper, $fields);
  $text = '';
  $length = 0;
  foreach ($fields as $field) {
    if (strlen($field['value']) > $length) {
      $text = $field['value'];
      $length = strlen($text);
    }
  }

  $output = '<h3>' . ($url ? l($name, $url['path'], $url['options']) : check_plain($name)) . "</h3>\n";
  if ($text) {
    $output .= text_summary($text);
  }

  return $output;
}
