<?php
/**
 * @file
 * Defines the theme functions for the Search API module.
 */

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\Element;
use Drupal\search_api\Utility\Utility;

/**
 * Returns HTML for a field list form.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: A render element representing the form.
 *
 * @return string
 *   The rendered HTML for a field list.
 */
function theme_search_api_admin_fields_table($variables) {
  $form = $variables['element'];
  $header = array(t('Field'), t('Machine name'), t('Indexed'), t('Type'), t('Boost'));

  $rows = array();
  if (!empty($form['fields'])) {
    foreach (Element::children($form['fields']) as $name) {
      $row = array();
      foreach (Element::children($form['fields'][$name]) as $field) {
        if ($cell = render($form['fields'][$name][$field])) {
          $row[] = $cell;
        }
      }
      if (empty($form['fields'][$name]['description']['#value'])) {
        $rows[] = Utility::deepCopy($row);
      }
      else {
        $rows[] = array(
          'data' => $row,
          'title' => strip_tags($form['fields'][$name]['description']['#value']),
        );
      }
    }
  }

  $note = isset($form['note']) ? $form['note'] : '';
  $submit = $form['submit'];
  $additional = isset($form['additional']) ? $form['additional'] : FALSE;
  unset($form['note'], $form['submit'], $form['additional']);
  $output = drupal_render_children($form);

  $build = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  $output .= drupal_render($build);
  $output .= render($note);
  $output .= render($submit);
  if ($additional) {
    $output .= render($additional);
  }

  return $output;
}

/**
 * Returns HTML for a Search API server.
 *
 * @param array $variables
 *   An associative array containing:
 *     - server:  A ServerInterface instance.
 *
 * @return string
 *   The rendered HTML for a Search API server.
 */
function theme_search_api_server($variables) {
  // Get the Search API server and index links.
  /** @var $index \Drupal\search_api\Server\ServerInterface */
  $server = $variables['server'];
  // Initialize the output variable to an empty string.
  $output = '';

  // Check if a description is available.
  if (($description = $server->getDescription())) {
    // Sanitiaze the description and append to the output.
    $output .= '<p class="description">' . nl2br(String::checkPlain($description)) . '</p>';
  }

  // Initialize the rows variable which will hold the different parts of server
  // information.
  $rows = array();
  // Create a row template with references so we don't have to deal with the
  // complicated structure for each inidividual row.
  $row = array(
    'data' => array(
      array('header' => TRUE),
      '',
    ),
    'class' => array(''),
  );
  // Get the individual parts of the row by reference.
  $label = &$row['data'][0]['data'];
  $info = &$row['data'][1];
  $class = &$row['class'][0];

  // Check if the server is enabled.
  if ($server->status()) {
    $class = 'ok';
    $info = t('enabled (!disable_link)', array('!disable_link' => l(t('disable'), $server->url('disable'))));
  }
  else {
    $class = 'warning';
    // Build the enable url.
    $enable_url = $server->url('enable');
    $parsed_url = UrlHelper::parse($enable_url);
    // Build the disabled message and use the enable link.
    $info = t('disabled (!enabled_link)', array('!enabled_link' => l(t('enable'), $parsed_url['path'], array('query' => $parsed_url['query']))));
  }
  // Append the row and reset variables.
  $label = t('Status');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if the service used by the server is valid.
  if ($server->hasValidService()) {
    // Get the service used by the server.
    /** @var $service \Drupal\search_api\Service\ServiceInterface */
    $service = $server->getService();
    // Get the service plugin definition.
    $service_plugin_definition = $service->getPluginDefinition();
    // Use the label defined in the plugin definition.
    $info = String::checkPlain($service_plugin_definition['label']);
  }
  else {
    $class = 'error';
    $info = t('Invalid or missing service plugin');
  }
  // Append the row and reset variables.
  $label = t('Service class');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Build the indexes links container.
  $indexes = array(
    '#theme' => 'links',
    '#attributes' => array('class' => array('inline')),
    '#links' => array(),
  );
  // Iterate through the attached indexes.
  foreach ($server->getIndexes() as $index) {
    // Build and append the link to the index.
    $indexes['#links'][] = array(
      'title' => $index->label(),
      'href' => $index->getSystemPath('canonical'),
    );
  }

  // Check if the indexes variable contains links.
  if (!empty($indexes['#links'])) {
    $label = t('Search indexes');
    $info = render($indexes);
    $rows[] = Utility::deepCopy($row);
  }

  // Check if the server has available view settings.
  if (($options = $server->viewSettings())) {
    $label = t('Service options');
    $info = render($options);
    $rows[] = Utility::deepCopy($row);
  }

  // Check if the server has extra information available.
  if (($extra = $server->getExtraInformation())) {
    // Iterate through the extra information.
    foreach ($extra as $information) {
      // Convert the extra information and append the information to the row.
      $label = $information['label'];
      $info = $information['info'];
      $class = (empty($information['status']) ? '' : $information['status']);
      $rows[] = Utility::deepCopy($row);
    }
  }

  // Build the server info table theme variables.
  $server_info_table = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#attributes' => array(
      'class' => array(
        'search-api-summary',
        'search-api-server-summary',
        'system-status-report',
      ),
    ),
  );
  // Render the server info table and append to the output.
  $output .= drupal_render($server_info_table);

  return $output;
}

/**
 * Returns HTML for a Search API index.
 *
 * @param array $variables
 *   An associative array containing:
 *     - index: An IndexInterface instance.
 *
 * @return string
 *   The rendered HTML for a Search API index.
 */
function theme_search_api_index($variables) {
  // Get the Search API index
  /** @var $index \Drupal\search_api\Index\IndexInterface */
  $index = $variables['index'];
  /** @var $server \Drupal\search_api\Server\ServerInterface */
  $server = $index->hasValidServer() ? $index->getServer() : NULL;
  /** @var $server \Drupal\search_api\DataSource\DataSourceInterface */
  $datasource = $index->hasValidDatasource() ? $index->getDatasource() : NULL;
  /** @var $tracker \Drupal\search_api\Tracker\TrackerInterface */
  $tracker = $index->hasValidTracker() ? $index->getTracker() : NULL;

  // Initialize the output variable to an empty string.
  $output = '';
  // Check if a description is available.
  if (($description = $index->getDescription())) {
    // Sanitiaze the description and append to the output.
    $output .= '<p class="description">' . nl2br(String::checkPlain($description)) . '</p>';
  }

  // Initialize the rows variable which will hold the different parts of server
  // information.
  $rows = array();
  // Create a row template with references so we don't have to deal with the
  // complicated structure for each inidividual row.
  $row = array(
    'data' => array(
      array('header' => TRUE),
      '',
    ),
    'class' => array(''),
  );
  // Get the individual parts of the row by reference.
  $label = &$row['data'][0]['data'];
  $info = &$row['data'][1];
  $class = &$row['class'][0];

  // Check if the index is enabled.
  if ($index->status()) {
    $class = 'ok';
    $info = t('enabled (!disable_link)', array('!disable_link' => l(t('disable'), $index->url('disable'))));
  }
  // Check if a server is available and enabled.
  elseif ($server && $server->status()) {
    $class = 'warning';
    // Build the enable url.
    $enable_url = $index->url('enable');
    $parsed_url = UrlHelper::parse($enable_url);
    // Build the disabled message and use the enable link.
    $info = t('disabled (!enabled_link)', array('!enabled_link' => l(t('enable'), $parsed_url['path'], array('query' => $parsed_url['query']))));
  }
  else {
    $class = 'warning';
    $info = t('disabled');
  }
  // Append the row and reset variables.
  $label = t('Status');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if a datasource is available.
  if ($datasource) {
    // Get the datasource plugin definition.
    $datasource_plugin_definition = $datasource->getPluginDefinition();
    // Use the label defined in the plugin definition.
    $info = $datasource_plugin_definition['label'];
  }
  else {
    $class = 'error';
    $info = t('Invalid or missing datasource plugin');
  }
  // Append the row and reset variables.
  $label = t('Item type');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if a tracker is available.
  if ($tracker) {
    // Get the tracker plugin definition.
    $tracker_plugin_definition = $tracker->getPluginDefinition();
    // Use the label defined in the plugin definition.
    $info = $tracker_plugin_definition['label'];
  }
  else {
    $class = 'error';
    $info = t('Invalid or missing tracker plugin');
  }
  // Append the row and reset variables.
  $label = t('Tracker');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if a server is available.
  if ($server) {
    $label = t('Server');
    $info = l($server->label(), $server->url('canonical'));
    $rows[] = Utility::deepCopy($row);
  }

  // Check if the index is enabled.
  if ($index->status()) {
    // Check if a valid server is available.
    if ($server) {
      // Create a default search query.
      $query = $index->query();
      // Configure the query so that we get a raw count of the index items
      // without any facets or filters.
      $query->fields(array())->range(0, 0);
      // Query the server and process the result.
      $response = $server->search($query);
      // Build the server index status translation variables.
      $vars = array('@url' => url('https://drupal.org/node/2009804#server-index-status'));
      // Build the server index status info.
      $label = t('Server index status');
      $info = \Drupal::translation()->formatPlural($response['result count'], 'There is 1 item indexed on the server for this index. (<a href="@url">More information</a>)', 'There are @count items indexed on the server for this index. (<a href="@url">More information</a>)', $vars);
      $rows[] = Utility::deepCopy($row);
    }
    // Get the configured options, merge in default options to prevent notice
    // when an index is missing the required options.
    $options = $index->getOptions() + array('cron_limit' => \Drupal::configFactory()->get('search_api.settings')->get('cron_limit'));
    // Check if the cron limit is higher then zero.
    if ($options['cron_limit'] > 0) {
      $class = 'ok';
      $info = \Drupal::translation()->formatPlural($options['cron_limit'], 'During cron runs, 1 item will be indexed per batch.', 'During cron runs, @count items will be indexed per batch.');
    }
    else {
      $class = 'warning';
      $info = t('No items will be indexed during cron runs.');
    }
    // Append the row and reset variables.
    $label = t('Cron batch size');
    $rows[] = Utility::deepCopy($row);
    $class = '';
    // Check if a valid tracker is available.
    if ($tracker) {
      // Get the indexed and total item count.
      $indexed_count = $tracker->getIndexedItemsCount();
      $total_count = $tracker->getTotalItemsCount();
      // Calculate the index progress.
      $percent = ($total_count !== 0) ? (100 * $indexed_count / $total_count) : 0;

      // Build the index progress bar.
      $index_progress = array(
        '#theme' => 'progress_bar',
        '#percent' => $percent,
        '#message' => t('@indexed/@total indexed', array('@indexed' => $indexed_count, '@total' => $total_count)),
      );
      // Add the progress bar to the output.
      $output .= '<h3>' . t('Index status') . '</h3>';
      $output .= '<div class="search-api-index-status">' . drupal_render($index_progress) . '</div>';
    }
  }

  // Build the index info table theme variables.
  $index_info_table = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#attributes' => array(
      'class' => array(
        'search-api-summary',
        'search-api-index-summary',
        'system-status-report',
      ),
    ),
  );
  // Render the server info table and append to the output.
  $output .= drupal_render($index_info_table);

  return $output;
}
