<?php
// $Id$

/**
 * Page callback that shows an overview of defined servers and indexes.
 */
// @todo Fix, indexes don't show up
function search_api_admin_overview() {
  $header = array(
    array('data' => t('Type'), 'colspan' => 2),
    array('data' => t('Name'), 'field' => 'name', 'first sort' => 'asc'),
    array('data' => t('Status'), 'field' => 'enabled', 'first sort' => 'desc',
        'sort' => 'desc'),
    array('data' => t('Operations'), 'colspan' => 3),
  );

  $servers = search_api_list_servers(FALSE, $header);
  $all_indexes = search_api_list_indexes(array('only_enabled' => FALSE));
  $indexes = array();
  foreach ($all_indexes as $index) {
    $indexes[$index->server][$index->id] = $index;
  }

  $rows = array();
  $t_server = array('data' => t('Server'), 'colspan' => 2);
  $t_index = t('Index');
  $t_enabled = '<strong>' . t('enabled') . '</strong>';
  $t_disabled = t('disabled');
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
    $link = l($server->name, $url);
    if ($server->enabled) {
      $link = '<strong>' . $link . '</strong>';
    }
    $rows[] = array(
      $t_server,
      $link,
      $server->enabled ? $t_enabled : $t_disabled,
      $server->enabled ? l($t_disable, $url . $disable)
          : l($t_enable, $url . $enable),
      l($t_edit, $url . $edit),
      l($t_delete, $url . $delete),
    );
    if (!empty($indexes[$server->id])) {
      foreach ($indexes[$server->id] as $index) {
        $url = $pre_index . $index->id;
        $link = l($index->name, $url);
        if ($index->enabled) {
          $link = '<strong>' . $link . '</strong>';
        }
        $rows[] = array(
          '–',
          $t_index,
          $link,
          $index->enabled ? $t_enabled : $t_disabled,
          $index->enabled ? l($t_disable, $url . $disable)
              : ($server->enabled ? l($t_enable, $url . $enable)
                  : '<del>' . $t_enable . '</del>'),
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
      if ($index->enabled) {
        $link = '<strong>' . $link . '</strong>';
      }
      $rows[] = array(
        array('data' => $t_index, 'colspan' => 2),
        $link,
        $t_disabled,
        '<del>' . $t_enable . '</del>',
        l($t_edit, $url . $edit),
        l($t_delete, $url . $delete),
      );
    }
  }

  return array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('There are no search servers defined yet.'),
  );
}

/**
 * Form callback showing a form for adding a server.
 */
function search_api_admin_add_server($form, &$form_state) {
  $form = array();
  $class = empty($form_state['values']['class']) ? ''
      : $form_state['values']['class'];

  if (empty($form_state['storage']['step_one'])) {
    $form['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#description' =>
          t('Select if the new server will be enabled after creation.'),
      '#default_value' => TRUE,
    );
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Server name'),
      '#description' => t('Enter the displayed name for the new server.'),
      '#maxlength' => 50,
      '#required' => TRUE,
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
  else if (!$class) {
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
      $service = new $info['class'];
    }
    if (!($service instanceof SearchApiServiceInterface)) {
      watchdog('search_api',
          t('Service class !id specifies an illegal class: !class',
          array('!id' => $id, '!class' => $info['class'])), NULL,
          WATCHDOG_ERROR);
      continue;
    }
    $form['options']['form'] = $service->configurationForm();
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
function search_api_admin_add_server_ajax_callback($form, &$form_state) {
  return $form['options'];
}

/**
 * Form submit callback for adding a server.
 */
function search_api_admin_add_server_submit($form, &$form_state) {
  $values = $form_state['values'];

  if (!empty($form_state['storage']['step_one'])) {
    $values += $form_state['storage']['step_one'];
    unset($form_state['storage']);
  }

  if (empty($values['options'])
      || ($values['class'] != $values['options']['class'])) {
    unset($values['options']);
    $form_state['storage']['step_one'] = $values;
    $form_state['rebuild'] = TRUE;
    drupal_set_message(t('Please configure the used service.'));
    return;
  }

  $values['options'] = $values['options']['form'];
  $obj = (object) $values;
  $id = search_api_server_insert($obj);
  if ($id) {
    $form_state['redirect'] = 'admin/config/search/search_api/server/' . $id;
  }
  else {
    drupal_set_message(t('An error occurred while trying to save the new ' .
        'server. Check the logs for details.'), 'error');
  }
}

/**
 * Displays a server's details.
 *
 * @param SearchApiServiceInterface $server
 *   The server to display.
 * @param $action
 *   One of 'enable', 'disable', 'delete'; or NULL if the server is only viewed.
 */
function search_api_admin_server_view(SearchApiServiceInterface $server = NULL,
    $action = NULL) {
  if (empty($server)) {
    return MENU_NOT_FOUND;
  }

  if (!empty($action)) {
    if ($action == 'enable') {
      if (search_api_server_enable($server)) {
        drupal_set_message(t('The server was successfully enabled.'));
      }
      else {
        drupal_set_message(t('The server could not be enabled. ' .
            'Check the logs for details.'), 'error');
      }
      drupal_goto('admin/config/search/search_api/server/' . $server->id);
    }
    else {
      $ret = drupal_get_form('search_api_admin_confirm', 'server', $action,
          $server);
    }
    if ($ret) {
      return $ret;
    }
  }

  $class = search_api_get_service_info($server->class);
  $option_form = $server->configurationForm();
  $option_names = array();
  foreach ($option_form as $key => $element) {
    if (isset($element['#title'])) {
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
    '#options' => $server->options,
    '#option_names' => $option_names,
  );
}

/**
 * Theme function for displaying a server or index.
 *
 * @param $variables
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
function theme_search_api_server($variables) {
  extract($variables);
  $output = '';

  $output .= '<h3>' . check_plain($name) . '</h3>' . "\n";

  $output .= '<dl>' . "\n";

  $output .= '<dt>' . t('Status') . '</dt>' . "\n";
  $output .= '<dd>';
  if ($enabled) {
    $output .= t('enabled (!disable_link)',
        array('!disable_link' => l(t('click to disable'),
        'admin/config/search/search_api/server/' . $id . '/disable')));
  }
  else {
    $output .= t('disabled (!enable_link)',
        array('!enable_link' => l(t('click to enable'),
        'admin/config/search/search_api/server/' . $id . '/enable')));
  }
  $output .= '</dd>' . "\n";

  if (!empty($description)) {
    $output .= '<dt>' . t('Description') . '</dt>' . "\n";
    $output .= '<dd>' . nl2br(check_plain($description)) . '</dd>' .
        "\n";
  }

  if (!empty($class_name)) {
    $output .= '<dt>' . t('Service class') . '</dt>' . "\n";
    $output .= '<dd>' . check_plain($class_name);
    if (!empty($class_description)) {
      $output .= '<p class="description">' .
          nl2br(check_plain($class_description)) . '</p>';
    }
    $output .= '</dd>' . "\n";
  }

  if (!empty($options)) {
    $output .= '<dt>' . t('Service options') . '</dt>' . "\n";
    $output .= '<dd><dl>' . "\n";
    foreach ($options as $key => $value) {
      $name = isset($option_names[$key]) ? $option_names[$key] : $key;
      $output .= '<dt>' . check_plain($name) . '</dt>' . "\n";
      $output .= '<dd>' . nl2br(check_plain(print_r($value, TRUE))) . '</dd>' .
          "\n";
    }
    $output .= '</dl></dd>' . "\n";
  }

  $output .= '</dl>';

  return $output;
}

/**
 * Edit a server's settings.
 *
 * @param SearchApiServiceInterface $server
 *   The server to edit.
 */
function search_api_admin_server_edit($form, &$form_state,
    SearchApiServiceInterface $server) {
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

  $form['options']['form'] = $service->configurationForm();
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
    '#value' => t('Create server'),
  );

  return $form;
}

/**
 * Submit function for search_api_admin_server_edit.
 */
function search_api_admin_server_edit_submit($form, &$form_state) {
  $values = $form_state['values'];
  $server = search_api_server_load($values['id']);

  unset($values['id']);
  unset($values['op']);

  $values['options'] = $values['options']['form'];

  $ret = search_api_server_edit($server, $values);
  if ($ret) {
    drupal_set_message(t('The search server was successfully edited.'));
    $form_state['redirect'] = 'admin/config/search/search_api/server/' .
        $server->id;
    return;
  }
  if ($ret === 0) {
    drupal_set_message(t('No values were changed.'));
    $form_state['redirect'] = 'admin/config/search/search_api/server/' .
        $server->id;
    return;
  }
  drupal_set_message(t('An error occurred while saving the changes. ' .
      'Check the logs for details.'));
  return;
}

/**
 * Form callback showing a form for adding an index.
 */
function search_api_admin_add_index($form, &$form_state) {
  $form = array();
  $form['entity_type'] = array(
    '#type' => 'select',
    '#title' => t('Entity type'),
    '#description' => t('Select the type of entity that will be indexed in ' .
            'this index. <strong>This setting cannot be changed afterwards!' .
            '</strong>'),
    '#default_value' => '',
    '#options' => array('' => t('- Select one -')),
    '#required' => TRUE,
  );
  foreach (entity_get_info() as $name => $entity) {
    $form['entity_type']['#options'][$name] = $entity['label'];
  }
  $form['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#description' => t('Select if the new index will be enabled after ' .
        'creation. This will only take effect if the selected server is also ' .
        'enabled.'),
    '#default_value' => TRUE,
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Index name'),
    '#description' => t('Enter the displayed name for the new index.'),
    '#maxlength' => 50,
    '#required' => TRUE,
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Index description'),
    '#description' => t('Enter a description for the new index.'),
  );
  $form['server'] = array(
    '#type' => 'select',
    '#title' => t('Server'),
    '#description' =>
        t('Select the server this index will initially reside on.'),
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
      $form['server']['#options'][$server->id] = $server->name . ' (' .
          t('disabled') . ')';
    }
  }
  $form['cron_limit'] = array(
    '#type' => 'textfield',
    '#title' => t('Cron limit'),
    '#description' => t('Set how many items will be indexed at most during ' .
        'each run of cron. "0" means that no items will be indexed by cron ' .
        'for this index, "-1" means that cron should index all items at once.'),
    '#default_value' => SEARCH_API_DEFAULT_CRON_LIMIT,
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Create server'),
  );

  return $form;
}

/**
 * Validation callback for search_api_admin_add_index.
 */
function search_api_admin_add_index_validate($form, &$form_state) {
  $cron_limit = $form_state['values']['cron_limit'];
  if ($cron_limit != '' . ((int) $cron_limit)) {
    // We don't enforce stricter rules and treat all negative values as -1.
    form_set_error('cron_limit', t('The cron limit must be a number.'));
  }
}

/**
 * Submit callback for search_api_admin_add_index.
 */
function search_api_admin_add_index_submit($form, &$form_state) {
  $values = (object) $form_state['values'];
  $values->options = array('cron_limit' => $values->cron_limit);
  unset($values->cron_limit);
  unset($values->op);

  // Trying to create an enabled index on a disabled server is handled elsewhere
  $id = search_api_index_insert($values);

  if ($id) {
    $form_state['redirect'] = 'admin/config/search/search_api/index/' . $id .
        '/workflow';
  }
  else {
    drupal_set_message(t('An error occurred while trying to save the new ' .
        'index. Check the logs for details.'), 'error');
  }
}

/**
 * Displays an index' details.
 *
 * @param stdClass $index
 *   The index to display.
 */
function search_api_admin_index_view(stdClass $index = NULL, $action = NULL) {
  if (empty($index)) {
    return MENU_NOT_FOUND;
  }

  if (!empty($action)) {
    if ($action == 'enable') {
      if (search_api_index_enable($index)) {
        drupal_set_message(t('The index was successfully enabled.'));
      }
      else {
        drupal_set_message(t('The index could not be enabled. ' .
            'Check the logs for details.'), 'error');
      }
      drupal_goto('admin/config/search/search_api/index/' . $index->id);
    }
    else {
      $ret = drupal_get_form('search_api_admin_confirm', 'index', $action,
          $index);
    }
    if ($ret) {
      return $ret;
    }
  }

  return '<pre>' . print_r($index, TRUE) . '</pre>';
}

/**
 * Edit an index' settings.
 *
 * @param stdClass $index
 *   The index to edit.
 */
function search_api_admin_index_edit($form, &$form_state, stdClass $index) {
  return array();
}

/**
 * Edit an index' workflow (data alter callbacks, pre-/postprocessors.
 *
 * @param stdClass $index
 *   The index to edit.
 */
function search_api_admin_index_workflow($form, &$form_state, stdClass $index) {
  return array();
}


/**
 * Helper function for displaying a generic confirmation form.
 *
 * @return
 *   Either a form array, or FALSE if this combination of type and action is
 *   not supported.
 */
function search_api_admin_confirm($form, &$form_state, $type, $action, $info) {
  switch ($type) {
    case 'server':
      switch ($action) {
        case 'disable':
          $text = array(
            t('Disable server !name', array('!name' => $info->name)),
            t('Do you really want to disable this server?'),
            t('This will disable both the server and all associated indexes. ' .
                "Searches on these indexes won't be available until they are " .
                're-enabled.'),
            t('The server and its indexes were successfully disabled.'),
          );
          break;
        case 'delete':
          $text = array(
            t('Delete server !name', array('!name' => $info->name)),
            t('Do you really want to delete this server?'),
            t('This will delete the server and disable all associated indexes. ' .
                "Searches on these indexes won't be available until they are " .
                'moved to another server and re-enabled.<br />' .
                '<strong>Use with extreme care!</strong>'),
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
            t('Disable index !name', array('!name' => $info->name)),
            t('Do you really want to disable this index?'),
            t("Searches on this index won't be available until it is re-enabled."),
            t('The index was successfully disabled.'),
          );
          break;
        case 'reindex':
          $text = array(
            t('Rebuild index !name', array('!name' => $info->name)),
            t('Do you really want to re-index the items in this index?'),
            t('This will add all items to the index again (overwriting the ' .
                'index), but existing items in the index will remain searchable.'),
            t('The index was successfully scheduled for re-indexing.'),
          );
          break;
        case 'clear':
          $text = array(
            t('Clear index !name', array('!name' => $info->name)),
            t('Do you really want to clear this index?'),
            t('All items will be deleted from the index and have to be ' .
                'inserted again by normally indexing them. Until all items ' .
                'are re-indexed, searches on this index will return ' .
                'incomplete results.<br />Use with care, in most cases ' .
                'rebuilding the index might be enough.'),
            t('The index was successfully cleared.'),
          );
          break;
        case 'delete':
          $text = array(
            t('Delete index !name', array('!name' => $info->name)),
            t('Do you really want to delete this index?'),
            t('This will remove the index from the server and delete all ' .
                'settings. All data on this index will be lost.<br />' .
                '<strong>Use with care!</strong>'),
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
  return confirm_form($form, $text[0],
      "admin/config/search/search_api/$type/{$info->id}", $desc, t('Confirm'),
      t('Cancel'));
}

/**
 * Submit function for search_api_admin_confirm().
 */
function search_api_admin_confirm_submit($form, &$form_state) {
  $values = $form_state['values'];

  $type = $values['type'];
  $action = $values['action'];
  $id = $values['id'];

  $function = "search_api_{$type}_{$action}";
  $load_function = "search_api_{$type}_load";
  $obj = $load_function($id);
  if ($obj && $function($obj)) {
    drupal_set_message($values['message']);
  }
  else {
    drupal_set_message(t('An error has occurred while performing the ' .
      'desired action. Check the logs for details.'), 'error');
  }

  $form_state['redirect'] = $action == 'delete'
      ? "admin/config/search/search_api"
      : "admin/config/search/search_api/$type/$id";
}
