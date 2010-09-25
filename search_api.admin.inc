<?php
// $Id$

/**
 * Page callback that shows an overview of defined servers and indexes.
 */
function search_api_admin_overview() {
  $base_path = drupal_get_path('module', 'search_api') . '/';
  drupal_add_css($base_path . 'search_api.admin.css');

  $header = array(
    // @todo Wait for http://drupal.org/node/109493
//    array('data' => t('Status'), 'field' => 'enabled', 'descending' => TRUE, 'sort' => 'desc'),
    array('data' => t('Status'), 'field' => 'enabled', 'sort' => 'desc'),
    array('data' => t('Type'), 'colspan' => 2),
    array('data' => t('Name'), 'field' => 'name'),
    array('data' => t('Operations'), 'colspan' => 3),
  );

  $servers = search_api_list_servers(FALSE, $header);
  $all_indexes = search_api_list_indexes(array('only_enabled' => FALSE), $header);
  $indexes = array();
  foreach ($all_indexes as $index) {
    $indexes[$index->server][$index->id] = $index;
  }

  $rows = array();
  $t_server = array('data' => t('Server'), 'colspan' => 2);
  $t_index = t('Index');
  $t_enabled['data'] = array(
    '#theme' => 'image',
    '#path' => $base_path . 'enabled.png',
    '#alt' => t('enabled'),
    '#title' => t('enabled'),
  );
  $t_enabled['class'] = array('search-api-status');
  $t_disabled['data'] = array(
    '#theme' => 'image',
    '#path' => $base_path . 'disabled.png',
    '#alt' => t('disabled'),
    '#title' => t('disabled'),
  );
  $t_disabled['class'] = array('search-api-status');
  $t_enable = t('enable');
  $t_disable = t('disable');
  $t_edit = t('edit');
  $t_delete = t('delete');
  $pre = 'admin/config/search/search_api/server/';
  $pre_index = 'admin/config/search/search_api/index/';
  $enable = '/enable';
  $disable = '/disable';
  $edit = '/edit';
  $delete = '/delete';
  foreach ($servers as $server) {
    $url = $pre . $server->id;
    $rows[] = array(
      $server->enabled ? $t_enabled : $t_disabled,
      $t_server,
      l($server->name, $url),
      $server->enabled ? l($t_disable, $url . $disable) : l($t_enable, $url . $enable),
      l($t_edit, $url . $edit),
      l($t_delete, $url . $delete),
    );
    if (!empty($indexes[$server->id])) {
      foreach ($indexes[$server->id] as $index) {
        $url = $pre_index . $index->id;
        $rows[] = array(
          $index->enabled ? $t_enabled : $t_disabled,
          '',
          $t_index,
          l($index->name, $url),
          $index->enabled
              ? l($t_disable, $url . $disable)
              : ($server->enabled ? l($t_enable, $url . $enable) : ''),
          l($t_edit, $url . $edit),
          l($t_delete, $url . $delete),
        );
      }
    }
  }
  if (!empty($indexes[''])) {
    foreach ($indexes[''] as $index) {
      $url = $pre_index . $index->id;
      $link = l($index->name, $url);
      $rows[] = array(
        $t_disabled,
        array('data' => $t_index, 'colspan' => 2),
        $link,
        '',
        l($t_edit, $url . $edit),
        l($t_delete, $url . $delete),
      );
    }
  }

  return array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('There are no search servers or indexes defined yet.'),
  );
}

/**
 * Form callback showing a form for adding a server.
 */
function search_api_admin_add_server(array $form, array &$form_state) {
  drupal_set_title(t('Add server'));

  $class = empty($form_state['values']['class']) ? '' : $form_state['values']['class'];

  if (empty($form_state['storage']['step_one'])) {
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Server name'),
      '#description' => t('Enter the displayed name for the new server.'),
      '#maxlength' => 50,
      '#required' => TRUE,
    );
    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#description' => t('Select if the new server will be enabled after creation.'),
      '#default_value' => TRUE,
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Server description'),
      '#description' => t('Enter a description for the new server.'),
    );
    $form['class'] = array(
      '#type' => 'select',
      '#title' => t('Service class'),
      '#description' => t('Choose a service class to use for this server.'),
      '#options' => array('' => '< '. t('Choose a service class') .' >'),
      '#required' => TRUE,
      '#default_value' => $class,
      '#ajax' => array(
        'callback' => 'search_api_admin_add_server_ajax_callback',
        'wrapper' => 'search-api-class-options',
      ),
    );
  }
  elseif (!$class) {
    $class = $form_state['storage']['step_one']['class'];
  }

  foreach (search_api_get_service_info() as $id => $info) {
    if (empty($form_state['storage']['step_one'])) {
      $form['class']['#options'][$id] = $info['name'];
    }

    if (!$class || $class != $id) {
      continue;
    }

    $service = NULL;
    if (class_exists($info['class'])) {
      $service = new $info['class']();
    }
    if (!($service instanceof SearchApiServiceInterface)) {
      watchdog('search_api', t('Service class !id specifies an illegal class: !class', array('!id' => $id, '!class' => $info['class'])), NULL, WATCHDOG_ERROR);
      continue;
    }
    $service_form = $service->configurationForm();
    $form['options']['form'] = $service_form ? $service_form : array('#markup' => t('There are no configuration options for this service class.'));
    $form['options']['class']['#type'] = 'value';
    $form['options']['class']['#value'] = $class;
    $form['options']['#type'] = 'fieldset';
    $form['options']['#tree'] = TRUE;
    $form['options']['#collapsible'] = TRUE;
    $form['options']['#title'] = $info['name'];
    $form['options']['#description'] = $info['description'];
  }
  $form['options']['#prefix'] = '<div id="search-api-class-options">';
  $form['options']['#suffix'] = '</div>';

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Create server'),
  );

  return $form;
}

/**
 * AJAX callback that just returns the "options" array of the already built form
 * array.
 */
function search_api_admin_add_server_ajax_callback(array $form, array &$form_state) {
  return $form['options'];
}

/**
 * Form validation callback for adding a server.
 *
 * Just calls the service class' validation handler.
 */
function search_api_admin_add_server_validate(array $form, array &$form_state) {
  if (empty($form_state['values']['options']['class'])) {
    return;
  }
  $class = $form_state['values']['options']['class'];
  $info = search_api_get_service_info($class);
  $service = NULL;
  if (class_exists($info['class'])) {
    $service = new $info['class']();
  }
  if (!($service instanceof SearchApiServiceInterface)) {
    form_set_error('class', t('There seems to be something wrong with the selected service class.'));
    return;
  }
  $form_state['values']['options']['service'] = $service;
  $service->configurationFormValidate($form['options']['form'], $form_state['values']['options']['form'], 'options][form][');
}

/**
 * Form submit callback for adding a server.
 */
function search_api_admin_add_server_submit(array $form, array &$form_state) {
  form_state_values_clean($form_state);
  $values = $form_state['values'];

  if (!empty($form_state['storage']['step_one'])) {
    $values += $form_state['storage']['step_one'];
    unset($form_state['storage']);
  }

  if (empty($values['options']) || ($values['class'] != $values['options']['class'])) {
    unset($values['options']);
    $form_state['storage']['step_one'] = $values;
    $form_state['rebuild'] = TRUE;
    drupal_set_message(t('Please configure the used service.'));
    return;
  }

  $service = $form_state['values']['options']['service'];
  $service->configurationFormSubmit($form['options']['form'], $values['options']['form']);
  $values['options'] = $values['options']['form'];
  $id = search_api_server_insert($values);
  $form_state['redirect'] = 'admin/config/search/search_api/server/' . $id;
  drupal_set_message(t('The server was successfully created.'));
}

/**
 * Title callback for viewing or editing a server or index.
 */
function search_api_admin_item_title($object) {
  return $object->name;
}

/**
 * Displays a server's details.
 *
 * @param SearchApiServer $server
 *   The server to display.
 * @param $action
 *   One of 'enable', 'disable', 'delete'; or NULL if the server is only viewed.
 */
function search_api_admin_server_view(SearchApiServer $server = NULL, $action = NULL) {
  if (empty($server)) {
    return MENU_NOT_FOUND;
  }

  if (!empty($action)) {
    if ($action == 'enable') {
      if (search_api_server_enable($server->id)) {
        drupal_set_message(t('The server was successfully enabled.'));
      }
      else {
        drupal_set_message(t('The server could not be enabled. Check the logs for details.'), 'error');
      }
      drupal_goto('admin/config/search/search_api/server/' . $server->id);
    }
    else {
      $ret = drupal_get_form('search_api_admin_confirm', 'server', $action, $server);
    }
    if ($ret) {
      return $ret;
    }
  }

  drupal_set_title(search_api_admin_item_title($server));
  $class = search_api_get_service_info($server->class);
  $option_form = $server->configurationForm();
  $option_names = array();
  $options = $server->options;
  foreach ($option_form as $key => $element) {
    if (isset($element['#title']) && isset($options[$key])) {
      $option_names[$key] = $element['#title'];
    }
  }
  return array(
    '#theme' => 'search_api_server',
    '#id' => $server->id,
    '#name' => $server->name,
    '#description' => $server->description,
    '#enabled' => $server->enabled,
    '#class_name' => $class['name'],
    '#class_description' => $class['description'],
    '#options' => $options,
    '#option_names' => $option_names,
  );
}

/**
 * Theme function for displaying a server.
 *
 * @param array $variables
 *   An associative array containing:
 *   - id: The server's id.
 *   - name: The server's name.
 *   - description: The server's description.
 *   - enabled: Boolean indicating whether the server is enabled.
 *   - class_name: The used service class' display name.
 *   - class_description: The used service class' description.
 *   - options: The service-specific user options as an array, keyed by field
 *     name.
 *   - option_names: The names of those options, as determined by the service
 *     configuration form.
 */
// @todo There may be some room for improvement here, in almost every respect.
function theme_search_api_server(array $variables) {
  extract($variables);
  $output = '';

  $output .= '<h3>' . check_plain($name) . '</h3>' . "\n";

  $output .= '<dl>' . "\n";

  $output .= '<dt>' . t('Status') . '</dt>' . "\n";
  $output .= '<dd>';
  if ($enabled) {
    $output .= t('enabled (!disable_link)', array('!disable_link' => l(t('disable'), 'admin/config/search/search_api/server/' . $id . '/disable')));
  }
  else {
    $output .= t('disabled (!enable_link)', array('!enable_link' => l(t('enable'), 'admin/config/search/search_api/server/' . $id . '/enable')));
  }
  $output .= '</dd>' . "\n";

  if (!empty($description)) {
    $output .= '<dt>' . t('Description') . '</dt>' . "\n";
    $output .= '<dd>' . nl2br(check_plain($description)) . '</dd>' . "\n";
  }

  if (!empty($class_name)) {
    $output .= '<dt>' . t('Service class') . '</dt>' . "\n";
    $output .= '<dd><em>' . check_plain($class_name) . '</em>';
    if (!empty($class_description)) {
      $output .= '<p class="description">' . nl2br(check_plain($class_description)) . '</p>';
    }
    $output .= '</dd>' . "\n";
  }

  if (!empty($option_names)) {
    $output .= '<dt>' . t('Service options') . '</dt>' . "\n";
    $output .= '<dd><dl>' . "\n";
    foreach ($option_names as $key => $name) {
      $value = $options[$key];
      $output .= '<dt>' . check_plain($name) . '</dt>' . "\n";
      $output .= '<dd>' . nl2br(check_plain(print_r($value, TRUE))) . '</dd>' . "\n";
    }
    $output .= '</dl></dd>' . "\n";
  }

  $output .= '</dl>';

  return $output;
}

/**
 * Edit a server's settings.
 *
 * @param SearchApiServer $server
 *   The server to edit.
 */
function search_api_admin_server_edit(array $form, array &$form_state, SearchApiServer $server) {
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Server name'),
    '#description' => t('Enter the displayed name for the  server.'),
    '#maxlength' => 50,
    '#default_value' => $server->name,
    '#required' => TRUE,
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Server description'),
    '#description' => t('Enter a description for the new server.'),
    '#default_value' => $server->description,
  );

  $class = search_api_get_service_info($server->class);

  $form['options']['form'] = $server->configurationForm();
  $form['options']['#type'] = 'fieldset';
  $form['options']['#tree'] = TRUE;
  $form['options']['#collapsible'] = TRUE;
  $form['options']['#title'] = $class['name'];
  $form['options']['#description'] = $class['description'];

  $form['id'] = array(
    '#type' => 'value',
    '#value' => $server->id,
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save settings'),
  );

  return $form;
}

/**
 * Validation function for search_api_admin_server_edit.
 */
function search_api_admin_server_edit_validate(array $form, array &$form_state) {
  $id = $form_state['values']['id'];
  $server = $form_state['values']['options']['server'] = search_api_server_load($id);
  $server->configurationFormValidate($form['options']['form'], $form_state['values']['options']['form'], 'options][form][');
}

/**
 * Submit function for search_api_admin_server_edit.
 */
function search_api_admin_server_edit_submit(array $form, array &$form_state) {
  form_state_values_clean($form_state);

  $values = $form_state['values'];
  $id = $values['id'];
  unset($values['id']);

  if (isset($values['options'])) {
    $server = $values['options']['server'];
    $values['options'] = $values['options']['form'];
    $server->configurationFormSubmit($form['options']['form'], $values['options']);
  }

  $ret = search_api_server_edit($id, $values);
  $form_state['redirect'] = 'admin/config/search/search_api/server/' . $id;
  if ($ret) {
    drupal_set_message(t('The search server was successfully edited.'));
  }
  else {
    drupal_set_message(t('No values were changed.'));
  }
}

/**
 * Form callback showing a form for adding an index.
 */
function search_api_admin_add_index(array $form, array &$form_state) {
  drupal_set_title(t('Add index'));

  $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Index name'),
    '#maxlength' => 50,
    '#required' => TRUE,
  );
  $form['entity_type'] = array(
    '#type' => 'select',
    '#title' => t('Entity type'),
    '#description' => t('Select the type of entity that will be indexed in this index. ' .
        'This setting cannot be changed afterwards.'),
    '#default_value' => '',
    '#options' => array('' => t('- Select one -')),
    '#required' => TRUE,
  );
  foreach (entity_get_info() as $name => $entity) {
    if (entity_metadata_get_info($name)) {
      $form['entity_type']['#options'][$name] = $entity['label'];
    }
  }
  $form['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#description' => t('This will only take effect if the selected server is also enabled.'),
    '#default_value' => TRUE,
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Index description'),
  );
  $form['server'] = array(
    '#type' => 'select',
    '#title' => t('Server'),
    '#description' => t('Select the server this index should reside on.'),
    '#default_value' => '',
    '#options' => array('' => t('< No server >'))
  );
  $servers = search_api_list_servers(FALSE);
  // List enabled servers first.
  foreach ($servers as $server) {
    if ($server->enabled) {
      $form['server']['#options'][$server->id] = $server->name;
    }
  }
  foreach ($servers as $server) {
    if (!$server->enabled) {
      $form['server']['#options'][$server->id] = $server->name . ' (' . t('disabled') . ')';
    }
  }
  $form['cron_limit'] = array(
    '#type' => 'textfield',
    '#title' => t('Cron limit'),
    '#description' => t('Set how many items will be indexed at most during each run of cron. ' .
        '"0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
    '#default_value' => SEARCH_API_DEFAULT_CRON_LIMIT,
    '#size' => 4,
    '#attributes' => array('class' => array('search-api-cron-limit')),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Create index'),
  );

  return $form;
}

/**
 * Validation callback for search_api_admin_add_index.
 */
function search_api_admin_add_index_validate(array $form, array &$form_state) {
  $cron_limit = $form_state['values']['cron_limit'];
  if ($cron_limit != '' . ((int) $cron_limit)) {
    // We don't enforce stricter rules and treat all negative values as -1.
    form_set_error('cron_limit', t('The cron limit must be a number.'));
  }
}

/**
 * Submit callback for search_api_admin_add_index.
 */
function search_api_admin_add_index_submit(array $form, array &$form_state) {
  form_state_values_clean($form_state);

  $values = $form_state['values'];
  $values['options'] = array('cron_limit' => $values['cron_limit']);
  unset($values['cron_limit']);

  // Trying to create an enabled index on a disabled server is handled elsewhere
  $id = search_api_index_insert($values);

  drupal_set_message(t('The index was successfully created. Please set up its workflow now.'));
  $form_state['redirect'] = 'admin/config/search/search_api/index/' . $id . '/workflow';
}

/**
 * Displays an index' details.
 *
 * @param SearchApiIndex $index
 *   The index to display.
 */
function search_api_admin_index_view(SearchApiIndex $index = NULL, $action = NULL) {
  if (empty($index)) {
    return MENU_NOT_FOUND;
  }

  if (!empty($action)) {
    if ($action == 'enable') {
      if (search_api_index_enable($index->id)) {
        drupal_set_message(t('The index was successfully enabled.'));
      }
      else {
        drupal_set_message(t('The index could not be enabled. Check the logs for details.'), 'error');
      }
      drupal_goto('admin/config/search/search_api/index/' . $index->id);
    }
    else {
      $ret = drupal_get_form('search_api_admin_confirm', 'index', $action, $index);
    }
    if ($ret) {
      return $ret;
    }
  }
  drupal_set_title(search_api_admin_item_title($index));

  $ret = array(
    '#theme' => 'search_api_index',
    '#id' => $index->id,
    '#name' => $index->name,
    '#description' => $index->description,
    '#entity_type' => $index->entity_type,
    '#enabled' => $index->enabled,
    '#server' => $index->server(),
    '#options' => $index->options,
  );

  return $ret;
}

/**
 * Theme function for displaying an index.
 *
 * @param array $variables
 *   An associative array containing:
 *   - id: The index's id.
 *   - name: The index' name.
 *   - description: The index' description.
 *   - entity_type: The type of entities stored in this index.
 *   - enabled: Boolean indicating whether the index is enabled.
 *   - server: The server this index currently rests on, if any.
 *   - options: The index' options, like cron limit.
 *   - indexed_items: The number of items already indexed in their latest
 *     version on this index.
 *   - total_items: The total number of items that have to be indexed for this
 *     index.
 */
// @todo There may be some room for improvement here, in almost every respect.
function theme_search_api_index(array $variables) {
  extract($variables);
  $output = '';

  $output .= '<h3>' . check_plain($name) . '</h3>' . "\n";

  $output .= '<dl>' . "\n";

  $output .= '<dt>' . t('Status') . '</dt>' . "\n";
  $output .= '<dd>';
  if ($enabled) {
    $output .= t('enabled (!disable_link)', array('!disable_link' => l(t('disable'), 'admin/config/search/search_api/index/' . $id . '/disable')));
  }
  elseif ($server && $server->enabled) {
    $output .= t('disabled (!enable_link)', array('!enable_link' => l(t('enable'), 'admin/config/search/search_api/index/' . $id . '/enable')));
  }
  else {
    $output .= t('disabled');
  }
  $output .= '</dd>' . "\n";

  $output .= '<dt>' . t('Entity type') . '</dt>' . "\n";
  $type = entity_get_info($entity_type);
  $type = $type['label'];
  $output .= '<dd>' . check_plain($type) . '</dd>' . "\n";

  if (!empty($description)) {
    $output .= '<dt>' . t('Description') . '</dt>' . "\n";
    $output .= '<dd>' . nl2br(check_plain($description)) . '</dd>' . "\n";
  }

  if (!empty($server)) {
    $output .= '<dt>' . t('Server') . '</dt>' . "\n";
    $output .= '<dd>' . l($server->name, 'admin/config/search/search_api/server/' . $server->id);
    if (!empty($server->description)) {
      $output .= '<p class="description">' . nl2br(check_plain($server->description)) . '</p>';
    }
    $output .= '</dd>' . "\n";
  }

  if (!empty($options)) {
    $output .= '<dt>' . t('Index options') . '</dt>' . "\n";
    $output .= '<dd><dl>' . "\n";
    $output .= '<dt>' . t('Cron limit') . '</dt>' . "\n";
    if (empty($options['cron_limit'])) {
      $output .= '<dd>' . t("Don't index during cron runs") . '</dd>' . "\n";
    }
    elseif ($options['cron_limit'] < 0) {
      $output .= '<dd>' . t('Unlimited') . '</dd>' . "\n";
    }
    else {
      $output .= '<dd>' . format_plural($options['cron_limit'], '1 item per cron run.', '@count items per cron run.') . '</dd>' . "\n";
    }

    if (!empty($options['fields'])) {
      $fields = array();
      foreach ($options['fields'] as $name => $field) {
        if ($field['indexed']) {
          if (search_api_is_text_type($field['type'])) {
            $fields[] = t('@field (@boost x)', array('@field' => $field['name'], '@boost' => $field['boost']));
          }
          else {
            $fields[] = $field['name'];
          }
        }
      }
      if ($fields) {
        $output .= '<dt>' . t('Indexed fields') . '</dt>' . "\n";
        $output .= '<dd>' . implode(', ', $fields) . '</dd>' . "\n";
      }
    }

    $output .= '</dl></dd>' . "\n";
  }

  $output .= '</dl>';

  return $output;
}

/**
 * Form function for displaying an index status form.
 *
 * @param SearchApiIndex $index
 *   The index whose status should be displayed.
 */
function search_api_admin_index_status_form(array $form, array &$form_state, SearchApiIndex $index) {
  $enabled = !empty($index->enabled);
  $status = search_api_index_status($index);
  $server = $index->server();

  $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';
  $form['id'] = array(
    '#type' => 'value',
    '#value' => $index->id,
  );

  $form['status_message'] = array(
    '#type' => 'item',
    '#title' => t('Status'),
    '#description' => $enabled ? t('The index is currently enabled.') : t('The index is currently disabled.'),
  );
  if (!empty($server->enabled)) {
    $form['status'] = array(
      '#type' => 'submit',
      '#value' => $enabled ? t('Disable') : t('Enable'),
    );
  }

  $form['progress'] = array(
    '#type' => 'item',
    '#title' => t('Progress'),
  );
  $all = ($status['indexed'] == $status['total']);
  if ($all) {
    $form['progress']['#description'] = t('All items have been indexed (@total / @total).',
        array('@total' => $status['total']));
  }
  elseif (!$status['indexed']) {
    $form['progress']['#description'] = t('All items still need to be indexed (@total total).',
        array('@total' => $status['total']));
  }
  else {
    $percentage = (int) (100 * $status['indexed'] / $status['total']);
    $form['progress']['#description'] = t('About @percentage% of all items have been indexed in their latest version (@indexed / @total).',
        array('@indexed' => $status['indexed'], '@total' => $status['total'], '@percentage' => $percentage));
  }

  if ($enabled && !$all) {
    $form['index'] = array(
      '#type' => 'fieldset',
      '#title' => t('Index now'),
      '#attributes' => array('class' => array('container-inline')),
      '#collapsible' => TRUE,
    );
    $form['index']['limit'] = array(
      '#type' => 'textfield',
      '#title' => t('Number of items:'),
      '#default_value' => empty($index->options['cron_limit']) ? SEARCH_API_DEFAULT_CRON_LIMIT : $index->options['cron_limit'],
      '#size' => 4,
      '#attributes' => array('class' => array('search-api-cron-limit')),
    );
    $form['index']['button'] = array(
      '#type' => 'submit',
      '#value' => t('Index now'),
    );
    $form['index']['total'] = array(
      '#type' => 'value',
      '#value' => $status['total'],
    );
    $form['index']['remaining'] = array(
      '#type' => 'value',
      '#value' => $status['total'] - $status['indexed'],
    );
  }

  if ($server) {
    if ($status['indexed'] > 0) {
      $form['reindex'] = array(
        '#type' => 'fieldset',
        '#title' => t('Re-indexing'),
        '#collapsible' => TRUE,
      );
      $form['reindex']['message'] = array(
        '#type' => 'item',
        '#description' => t('This will add all items to the index again (overwriting the index), but existing items in the index will remain searchable.'),
      );
      $form['reindex']['button'] = array(
        '#type' => 'submit',
        '#value' => t('Re-index content'),
      );
    }

    $form['clear'] = array(
      '#type' => 'fieldset',
      '#title' => t('Clear index'),
      '#collapsible' => TRUE,
    );
    $form['clear']['message'] = array(
      '#type' => 'item',
      '#description' => t('All items will be deleted from the index and have to be inserted again by normally indexing them. ' .
          'Until all items are re-indexed, searches on this index will return incomplete results.<br />' .
          'Use with care, in most cases rebuilding the index might be enough.'),
    );
    $form['clear']['button'] = array(
      '#type' => 'submit',
      '#value' => t('Clear index'),
    );
  }

  return $form;
}

/**
 * Validation function for search_api_admin_index_status_form.
 */
function search_api_admin_index_status_form_validate(array $form, array &$form_state) {
  if ($form_state['values']['op'] == t('Index now') && !$form_state['values']['limit']) {
    form_set_error('number', t('You have to set the number of items to index. Set to -1 for indexing all items.'));
  }
}

/**
 * Submit function for search_api_admin_index_status_form.
 */
function search_api_admin_index_status_form_submit(array $form, array &$form_state) {
  $redirect = &$form_state['redirect'];
  $values = $form_state['values'];
  $id = $values['id'];
  $base = 'admin/config/search/search_api';
  $pre = $base . '/index/' . $id;
  switch ($values['op']) {
    case t('Enable'):
      $redirect = $pre . '/enable';
      break;
    case t('Disable'):
      $redirect = $pre . '/disable';
      break;
    case t('Index now'):
      $index = search_api_index_load($id);
      if ($index) {
        $limit = $values['limit'];
        // @todo Batch API?
        $ret = search_api_index_items($index, $limit);
        if ($ret) {
          drupal_set_message(format_plural($ret, 'Successfully indexed 1 item.', 'Successfully indexed @count items.'));
          if (($limit > 0 && $ret < min(array($limit, $values['remaining'])))
              || ($limit < 0 && $ret < $values['remaining'])) {
            drupal_set_message(t("Some items couldn't be indexed. Check the logs for details."), 'warning');
          }
        }
        else {
          drupal_set_message(t("Couldn't index items. Check the logs for details."), 'error');
        }
        $redirect = $pre . '/status';
      }
      else {
        drupal_set_message(t('This index does not exist.'), 'warning');
        $redirect = $base;
      }
      break;
    case t('Re-index content'):
      if (search_api_index_reindex($id)) {
        drupal_set_message(t('The index was successfully scheduled for re-indexing.'));
      }
      else {
        drupal_set_message(t('An error has occurred while performing the desired action. Check the logs for details.'), 'error');
      }
      $redirect = $pre . '/status';
      break;
    case t('Clear index'):
      if (search_api_index_clear($id)) {
        drupal_set_message(t('The index was successfully cleared.'));
      }
      else {
        drupal_set_message(t('An error has occurred while performing the desired action. Check the logs for details.'), 'error');
      }
      $redirect = $pre . '/status';
      break;

    default:
      throw new SearchApiException(t('Unknown action.'));
  }
}

/**
 * Edit an index' settings.
 *
 * @param SearchApiIndex $index
 *   The index to edit.
 */
function search_api_admin_index_edit(array $form, array &$form_state, SearchApiIndex $index) {
  $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Index name'),
    '#maxlength' => 50,
    '#default_value' => $index->name,
    '#required' => TRUE,
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Index description'),
    '#default_value' => $index->description,
  );
  $form['server'] = array(
    '#type' => 'select',
    '#title' => t('Server'),
    '#description' => t('Select the server this index should reside on.'),
    '#default_value' => $index->server,
    '#options' => array('' => t('< No server >'))
  );
  $servers = search_api_list_servers(FALSE);
  // List enabled servers first.
  foreach ($servers as $server) {
    if ($server->enabled) {
      $form['server']['#options'][$server->id] = $server->name;
    }
  }
  foreach ($servers as $server) {
    if (!$server->enabled) {
      $form['server']['#options'][$server->id] =  t('@server_name (disabled)', array('@server_name' => $server->name));
    }
  }
  $form['cron_limit'] = array(
    '#type' => 'textfield',
    '#title' => t('Cron limit'),
    '#description' => t('Set how many items will be indexed at most during each run of cron. ' .
        '"0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
    '#default_value' => isset($index->options['cron_limit']) ? $index->options['cron_limit'] : SEARCH_API_DEFAULT_CRON_LIMIT,
    '#size' => 4,
    '#attributes' => array('class' => array('search-api-cron-limit')),
  );

  $form['id'] = array(
    '#type' => 'value',
    '#value' => $index->id,
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save settings'),
  );

  return $form;
}

/**
 * Validation callback for search_api_admin_index_edit.
 */
function search_api_admin_index_edit_validate(array $form, array &$form_state) {
  $cron_limit = $form_state['values']['cron_limit'];
  if ($cron_limit != '' . ((int) $cron_limit)) {
    // We don't enforce stricter rules and treat all negative values as -1.
    form_set_error('cron_limit', t('The cron limit must be a number.'));
  }
}

/**
 * Submit callback for search_api_admin_index_edit.
 */
function search_api_admin_index_edit_submit(array $form, array &$form_state) {
  form_state_values_clean($form_state);

  $values = $form_state['values'];
  $index = search_api_index_load($values['id']);
  $values['options'] = $index->options;
  $values['options']['cron_limit'] = $values['cron_limit'];
  unset($values['cron_limit']);
  unset($values['id']);

  $ret = search_api_index_edit($index->id, $values);
  $form_state['redirect'] = 'admin/config/search/search_api/index/' . $index->id;
  if ($ret) {
    drupal_set_message(t('The search index was successfully edited.'));
  }
  else {
    drupal_set_message(t('No values were changed.'));
  }
}

/**
 * Edit an index' workflow (data alter callbacks, pre-/postprocessors, and their
 * order).
 *
 * @param SearchApiIndex $index
 *   The index to edit.
 */
// Copied from filter_admin_format_form
function search_api_admin_index_workflow(array $form, array &$form_state, SearchApiIndex $index) {
  $callback_info = search_api_get_alter_callbacks();
  $processor_info = search_api_get_processors();
  $options = empty($index->options) ? array() : $index->options;

  $form['#tree'] = TRUE;
  $form['#attached']['js'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.js';

  // Callbacks

  $callbacks = empty($options['data_alter_callbacks']) ? array() : $options['data_alter_callbacks'];
  foreach ($callback_info as $name => $callback) {
    if (!isset($callbacks[$name])) {
      $callbacks[$name]['status'] = 0;
      $callbacks[$name]['weight'] = $callback['weight'];
    }
  }
  $form['#callbacks'] = $callbacks;
  $form['callbacks'] = array(
    '#type' => 'fieldset',
    '#title' => t('Data-alter callbacks'),
    '#description' => t('Select the callbacks that will be executed on indexed items, and their order.'),
    '#collapsible' => TRUE,
  );

  // Callback status.
  $form['callbacks']['status'] = array(
    '#type' => 'item',
    '#title' => t('Enabled data-alter callbacks'),
    '#prefix' => '<div class="search-api-status-wrapper">',
    '#suffix' => '</div>',
  );
  foreach ($callback_info as $name => $callback) {
    $form['callbacks']['status'][$name] = array(
      '#type' => 'checkbox',
      '#title' => $callback['name'],
      '#default_value' => $callbacks[$name]['status'],
      '#parents' => array('callbacks', $name, 'status'),
      '#description' => $callback['description'],
      '#weight' => $callback['weight'],
    );
  }

  // Callback order (tabledrag).
  $form['callbacks']['order'] = array(
    '#type' => 'item',
    '#title' => t('Data-alter callback processing order'),
    '#theme' => 'search_api_admin_item_order',
    '#table_id' => 'search-api-callbacks-order-table',
  );
  foreach ($callback_info as $name => $callback) {
    $form['callbacks']['order'][$name]['item'] = array(
      '#markup' => $callback['name'],
    );
    $form['callbacks']['order'][$name]['weight'] = array(
      '#type' => 'weight',
      '#delta' => 50,
      '#default_value' => $callbacks[$name]['weight'],
      '#parents' => array('callbacks', $name, 'weight'),
    );
    $form['callbacks']['order'][$name]['#weight'] = $callbacks[$name]['weight'];
  }

  // Processors

  $processors = empty($options['processors']) ? array() : $options['processors'];
  $processor_objects = array();
  foreach ($processor_info as $name => $processor) {
    if (!isset($processors[$name])) {
      $processors[$name]['status'] = 0;
      $processors[$name]['weight'] = $processor['weight'];
    }
    $settings = empty($processors[$name]['settings']) ? array() : $processors[$name]['settings'];
    if (class_exists($processor['class'])) {
      $processor_objects[$name] = new $processor['class']($index, $settings);
    }
    if (!(class_exists($processor['class']) && $processor_objects[$name] instanceof SearchApiProcessorInterface)) {
      watchdog('search api', t('Processor !id specifies illegal processor class !class.', array('!id' => $name, '!class' => $processor['class'])), NULL, WATCHDOG_WARNING);
      unset($processor_info[$name]);
      unset($processors[$name]);
      unset($processor_objects[$name]);
      continue;
    }
  }
  $form['#processors'] = $processors;
  $form['processors'] = array(
    '#type' => 'fieldset',
    '#title' => t('Processors'),
    '#description' => t('Select processors which will pre- and post-process data at index and search time, and their order.'),
    '#collapsible' => TRUE,
  );

  // Processor status.
  $form['processors']['status'] = array(
    '#type' => 'item',
    '#title' => t('Enabled processors'),
    '#prefix' => '<div class="search-api-status-wrapper">',
    '#suffix' => '</div>',
  );
  foreach ($processor_info as $name => $processor) {
    $form['processors']['status'][$name] = array(
      '#type' => 'checkbox',
      '#title' => $processor['name'],
      '#default_value' => $processors[$name]['status'],
      '#parents' => array('processors', $name, 'status'),
      '#description' => $processor['description'],
      '#weight' => $processor['weight'],
    );
  }

  // Processor order (tabledrag).
  $form['processors']['order'] = array(
    '#type' => 'item',
    '#title' => t('Processor processing order'),
    '#description' => t('Set the order in which preprocessing will be done at index and search time. ' .
        'Postprocessing of search results will be in the exact opposite direction.'),
    '#theme' => 'search_api_admin_item_order',
    '#table_id' => 'search-api-processors-order-table',
  );
  foreach ($processor_info as $name => $processor) {
    $form['processors']['order'][$name]['item'] = array(
      '#markup' => $processor['name'],
    );
    $form['processors']['order'][$name]['weight'] = array(
      '#type' => 'weight',
      '#delta' => 50,
      '#default_value' => $processors[$name]['weight'],
      '#parents' => array('processors', $name, 'weight'),
    );
    $form['processors']['order'][$name]['#weight'] = $processors[$name]['weight'];
  }

  // Processor settings.
  $form['processors']['settings_title'] = array(
    '#type' => 'item',
    '#title' => t('Processor settings'),
  );
  $form['processors']['settings'] = array(
    '#type' => 'vertical_tabs',
  );

  foreach ($processor_info as $name => $processor) {
    $settings_form = $processor_objects[$name]->configurationForm();
    if (!empty($settings_form)) {
      $form['processors']['settings'][$name] = array(
        '#type' => 'fieldset',
        '#title' => $processor['name'],
        '#parents' => array('processors', $name, 'settings'),
        '#weight' => $processor['weight'],
        '#group' => 'processors_settings',
      );
      $form['processors']['settings'][$name] += $settings_form;
    }
  }

  $form['id'] = array('#type' => 'value', '#value' => $index->id);
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save configuration'));

  return $form;
}

/**
 * Returns HTML for a processor/callback order form.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: A render element representing the form.
 */
function theme_search_api_admin_item_order(array $variables) {
  $element = $variables['element'];

  $rows = array();
  foreach (element_children($element, TRUE) as $name) {
    $element[$name]['weight']['#attributes']['class'][] = 'search-api-order-weight';
    $rows[] = array(
      'data' => array(
        drupal_render($element[$name]['item']),
        drupal_render($element[$name]['weight']),
      ),
      'class' => array('draggable'),
    );
  }
  $output = drupal_render_children($element);
  $output .= theme('table', array('rows' => $rows, 'attributes' => array('id' => $element['#table_id'])));
  drupal_add_tabledrag($element['#table_id'], 'order', 'sibling', 'search-api-order-weight', NULL, NULL, TRUE);

  return $output;
}

/**
 * Submit callback for search_api_admin_index_workflow.
 */
function search_api_admin_index_workflow_submit(array $form, array &$form_state) {
  $values = $form_state['values'];
  unset($values['processors']['settings']);
  $index = search_api_index_load($values['id']);

  $options = empty($index->options) ? array() : $index->options;
  $options['data_alter_callbacks'] = $values['callbacks'];
  $options['processors'] = $values['processors'];

  // Save the already sorted arrays to avoid having to sort them at each use.
  uasort($options['data_alter_callbacks'], 'search_api_admin_element_compare');
  uasort($options['processors'], 'search_api_admin_element_compare');

  $ret = search_api_index_edit($index->id, array('options' => $options));
  if ($ret) {
    search_api_index_reindex($index->id);
    drupal_set_message(t("The search index' workflow was successfully edited. " .
        'All content was scheduled for re-indexing so the new settings can take effect.'));
  }
  else {
    drupal_set_message(t('No values were changed.'));
  }

  if (empty($options['fields'])) {
    drupal_set_message(t('Please set the fields that should be indexed.'));
    $form_state['redirect'] = 'admin/config/search/search_api/index/' . $index->id . '/fields';
  }
  else {
    $form_state['redirect'] = 'admin/config/search/search_api/index/' . $index->id;
  }
}

/**
 * Sort callback sorting array elements by their "weight" key, if present.
 *
 * @see element_sort
 */
function search_api_admin_element_compare($a, $b) {
  $a_weight = (is_array($a) && isset($a['weight'])) ? $a['weight'] : 0;
  $b_weight = (is_array($b) && isset($b['weight'])) ? $b['weight'] : 0;
  if ($a_weight == $b_weight) {
    return 0;
  }
  return ($a_weight < $b_weight) ? -1 : 1;
}

/**
 * Select the indexed fields.
 *
 * @param SearchApiIndex $index
 *   The index to edit.
 */
function search_api_admin_index_fields(array $form, array &$form_state, SearchApiIndex $index) {
  $array = array();
  $index->dataAlter($array);
  $wrapper = entity_metadata_wrapper($index->entity_type, NULL, array('property info alter' => array($index, 'propertyInfoAlter')));

  $options = _search_api_admin_get_fields($index, $wrapper);
  $fields = $options['fields'];
  $additional = $options['additional fields'];

  $types = search_api_field_types();
  $list_types = array();
  foreach ($types as $type => $name) {
    $list_types["list<$type>"] = $name;
  }
  $boosts = drupal_map_assoc(array('0.1', '0.2', '0.3', '0.5', '0.8', '1.0', '2.0', '3.0', '5.0', '8.0', '13.0', '21.0'));

  $form['#theme'] = 'search_api_admin_fields_table';
  $form['#tree'] = TRUE;
  $form['description'] = array(
    '#type' => 'item',
    '#title' => t('Select fields to index'),
    '#description' => t('<p>The datatype of fields determines, how they can be searched or filtered upon. ' .
        'The boost is used to give additional weight to certain fields, e.g. titles or tags. It only takes effect for fulltext fields.</p>' .
        '<p>Whether detailled field types are supported depends on the type of server this index resides on. ' .
        "Check the server's service class' documentation for details.<br />" .
        'In any case, fields of type "Fulltext" will always be fulltext-searchable.</p>'),
  );
  foreach ($fields as $key => $info) {
    $form['fields'][$key]['title']['#markup'] = check_plain($info['name']);
    $form['fields'][$key]['name'] = array(
      '#type' => 'value',
      '#value' => $info['name'],
    );
    if (isset($info['description'])) {
      $form['fields'][$key]['description'] = array(
        '#type' => 'value',
        '#value' => $info['description'],
      );
    }
    $form['fields'][$key]['type'] = array(
      '#type' => 'select',
      '#options' => search_api_is_list_type($info['type']) ? $list_types : $types,
      '#default_value' => $info['type'],
    );
    $form['fields'][$key]['boost'] = array(
      '#type' => 'select',
      '#options' => $boosts,
      '#default_value' => $info['boost'],
    );
    $form['fields'][$key]['indexed'] = array(
      '#type' => 'checkbox',
      '#default_value' => $info['indexed'],
    );
    if ($key == 'search_api_language') {
      // Is treated specially to always index the language.
      $form['fields'][$key]['type']['#default_value'] = 'string';
      $form['fields'][$key]['type']['#disabled'] = TRUE;
      $form['fields'][$key]['boost']['#default_value'] = '1.0';
      $form['fields'][$key]['boost']['#disabled'] = TRUE;
      $form['fields'][$key]['indexed']['#default_value'] = 1;
      $form['fields'][$key]['indexed']['#disabled'] = TRUE;
    }
  }

  $form['id'] = array(
    '#type' => 'value',
    '#value' => $index->id,
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save changes'),
  );

  if ($additional) {
    $form['additional'] = array(
      '#type' => 'fieldset',
      '#title' => t('Add related entity'),
      '#description' => t('There are entities related to entities of this type. ' .
          'You can add their fields to the list above so they can be indexed too.') . '<br />',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
      'field' => array(
        '#type' => 'select',
        '#options' => $additional,
      ),
      'add' => array(
        '#type' => 'submit',
        '#value' => t('Add fields'),
      )
    );
  }

  // @todo Provide a way to remove a related entity's fields again?

  return $form;
}

/**
 * Helper function for building the field list for an index.
 */
function _search_api_admin_get_fields(SearchApiIndex $index, EntityMetadataWrapper $wrapper) {
  if (empty($index->options['fields'])) {
    $index->options['fields'] = array();
  }
  $fields = $index->options['fields'];
  $additional = array();

  // First we need all already added prefixes.
  $added = array();
  foreach (array_keys($fields) as $key) {
    $key = substr($key, 0, strrpos($key, ':'));
    $added[$key] = TRUE;
  }

  // Then we walk through all properties and look if they are already contained in one of the arrays.
  // Since this uses an iterative instead of a recursive approach, it is a bit complicated,
  // with two arrays tracking the current depth.

  // A wrapper for a specific field name prefix, e.g. 'user:' mapped to the user wrapper
  $wrappers = array('' => $wrapper);
  // Display names for the prefixes
  $prefix_names = array('' => '');

  $types = search_api_field_types();
  $flat = array();
  while ($wrappers) {
    foreach ($wrappers as $prefix => $wrapper) {
      $prefix_name = $prefix_names[$prefix];
      foreach ($wrapper as $property => $value) {
        $info = $value->info();
        $type = $info['type'];
        // We hide the complexity of multi-valued types from the user here
        $type = search_api_extract_inner_type($type);
        if ($type == 'token') {
          $type = 'string';
        }
        $key = $prefix . $property;
        if (isset($types[$type])) {
          if (isset($fields[$key])) {
            $fields[$key]['name'] = $prefix_name . $info['label'];
            $fields[$key]['description'] = empty($info['description']) ? NULL : $info['description'];
            $flat[$key] = $fields[$key];
          }
          else {
            $flat[$key] = array(
              'name'    => $prefix_name . $info['label'],
              'description' => empty($info['description']) ? NULL : $info['description'],
              'type'    => $info['type'],
              'boost' => '1.0',
              'indexed' => 0,
            );
          }
        }
        else {
          if (isset($added[$key])) {
            // Visit this entity/struct in a later iteration.
            $wrappers[$key . ':'] = $value;
            $prefix_names[$key . ':'] = $prefix_name . $info['label'] . ' » ';
          }
          elseif (!search_api_is_list_type($type)) {
            // @todo Make this work with lists, too - this won't be easy:
            // -> Obtain wrapper for listed type (may even be a struct!)
            // -> When adding fields, change all types to lists
            // -> Somehow spot this while indexing
            $additional[$key] = $prefix_name . $info['label'];
          }
        }
      }
      unset($wrappers[$prefix]);
    }
  }

  $options = array();
  $options['fields'] = $flat;
  $options['additional fields'] = $additional;
  return $options;
}

/**
 * Returns HTML for a field list form.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: A render element representing the form.
 */
function theme_search_api_admin_fields_table($variables) {
  $form = $variables['element'];
  $header = array(t('Field'), t('Type'), t('Boost'), t('Indexed'));

  $rows = array();
  foreach (element_children($form['fields']) as $name) {
    $row = array();
    foreach (element_children($form['fields'][$name]) as $field) {
      if ($cell = render($form['fields'][$name][$field])) {
        $row[] = $cell;
      }
    }
    if (empty($form['fields'][$name]['description']['#value'])) {
      $rows[] = $row;
    }
    else {
      $rows[] = array(
        'data' => $row,
        'title' => $form['fields'][$name]['description']['#value'],
      );
    }
  }

  $submit = $form['submit'];
  $additional = isset($form['additional']) ? $form['additional'] : FALSE;
  unset($form['submit'], $form['additional']);
  $output = drupal_render_children($form);
  $output .= theme('table', array('header' => $header, 'rows' => $rows));
  $output .= render($submit);
  if ($additional) {
    $output .= render($additional);
  }

  return $output;
}

/**
 * Submit function for search_api_admin_index_fields.
 */
function search_api_admin_index_fields_submit(array $form, array &$form_state) {
  $id = $form_state['values']['id'];
  if ($form_state['values']['op'] == t('Save changes')) {
    $fields = $form_state['values']['fields'];
    foreach ($fields as $name => &$field) {
      unset($field['description']);
    }
    $ret = search_api_index_edit_fields($id, $fields);

    $form_state['redirect'] = 'admin/config/search/search_api/index/' . $id;
    if ($ret) {
      drupal_set_message(t('The indexed fields were successfully changed. ' .
          'The index was cleared and will have to be re-indexed with the new settings.'));
    }
    else {
      drupal_set_message(t('No values were changed.'));
    }
    return;
  }
  // Adding a related entity's fields
  $index = search_api_index_load($id);

  $wrapper = $orig_wrapper = entity_metadata_wrapper($index->entity_type);
  $prefix = $form_state['values']['additional']['field'];
  $types = search_api_field_types();

  foreach (explode(':', $prefix) as $element) {
    if (!isset($wrapper->$element)) {
      $wrapper = NULL;
      break;
    }
    $wrapper = $wrapper->$element;
  }
  if (!$wrapper) {
    drupal_set_message(t("An error occurred while trying to add the related entity's fields."), 'error');
    return;
  }

  if (!empty($index->options['fields'])) {
    $fields = $index->options['fields'];
  }
  else {
    $fields = _search_api_admin_get_fields($index, $orig_wrapper);
    $fields = $fields['fields'];
  }

  $prefix .= ':';
  foreach ($wrapper as $field => $value) {
    $info = $value->info();
    $key = $prefix . $field;
    if (isset($types[$info['type']]) && empty($fields[$key])) {
      $fields[$key] = array('type' => $info['type'], 'boost' => '1.0', 'indexed' => 0);
    }
  }
  $index->options['fields'] = $fields;
  $ret = search_api_index_edit($id, array('options' => $index->options));

  if ($ret) {
    drupal_set_message(t('The available fields were successfully changed.'));
  }
  else {
    drupal_set_message(t('No values were changed.'));
  }
  $form_state['redirect'] = 'admin/config/search/search_api/index/' . $id . '/fields';
}


/**
 * Helper function for displaying a generic confirmation form.
 *
 * @return
 *   Either a form array, or FALSE if this combination of type and action is
 *   not supported.
 */
function search_api_admin_confirm(array $form, array &$form_state, $type, $action, $info) {
  switch ($type) {
    case 'server':
      switch ($action) {
        case 'disable':
          $text = array(
            t('Disable server @name', array('@name' => $info->name)),
            t('Do you really want to disable this server?'),
            t('This will disable both the server and all associated indexes. ' .
                "Searches on these indexes won't be available until they are re-enabled."),
            t('The server and its indexes were successfully disabled.'),
          );
          break;
        case 'delete':
          $text = array(
            t('Delete server @name', array('@name' => $info->name)),
            t('Do you really want to delete this server?'),
            t('This will delete the server and disable all associated indexes. ' .
                "Searches on these indexes won't be available until they are moved to another server and re-enabled."),
            t('The server was successfully deleted.'),
          );
          break;
        default:
          return FALSE;
      }
      break;
    case 'index':
      switch ($action) {
        case 'disable':
          $text = array(
            t('Disable index @name', array('@name' => $info->name)),
            t('Do you really want to disable this index?'),
            t("Searches on this index won't be available until it is re-enabled."),
            t('The index was successfully disabled.'),
          );
          break;
        case 'delete':
          $text = array(
            t('Delete index @name', array('@name' => $info->name)),
            t('Do you really want to delete this index?'),
            t('This will remove the index from the server and delete all settings. ' .
                'All data on this index will be lost.'),
            t('The index has been successfully deleted.'),
          );
          break;
        default:
          return FALSE;
      }
      break;
    default:
      return FALSE;
  }

  $form = array(
    'type' => array(
      '#type' => 'value',
      '#value' => $type,
    ),
    'action' => array(
      '#type' => 'value',
      '#value' => $action,
    ),
    'id' => array(
      '#type' => 'value',
      '#value' => $info->id,
    ),
    'message' => array(
      '#type' => 'value',
      '#value' => $text[3],
    ),
  );
  $desc = "<h3>{$text[1]}</h3><p>{$text[2]}</p>";
  return confirm_form($form, $text[0], "admin/config/search/search_api/$type/{$info->id}", $desc);
}

/**
 * Submit function for search_api_admin_confirm().
 */
function search_api_admin_confirm_submit(array $form, array &$form_state) {
  $values = $form_state['values'];

  $type = $values['type'];
  $action = $values['action'];
  $id = $values['id'];

  $function = "search_api_{$type}_{$action}";
  if ($function($id)) {
    drupal_set_message($values['message']);
  }
  else {
    drupal_set_message(t('An error has occurred while performing the desired action. Check the logs for details.'), 'error');
  }

  $form_state['redirect'] = $action == 'delete'
      ? "admin/config/search/search_api"
      : "admin/config/search/search_api/$type/$id";
}
