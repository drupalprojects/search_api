<?php
// $Id$

/**
 * Page callback that shows an overview of defined servers and indexes.
 */
function search_api_admin_overview() {
  $header = array(
    array('data' => t('Type'), 'colspan' => 2),
    array('data' => t('Name'), 'field' => 'name'),
    array('data' => t('Status'), 'field' => 'enabled', 'sort' => 'desc'),
    array('data' => t('Operations'), 'colspan' => 3),
  );

  $servers = search_api_list_servers(FALSE, $header);
  $all_indexes = search_api_list_indexes(array('enabled' => FALSE));
  $indexes = array();
  foreach ($indexes as $index) {
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
              : l($t_enable, $url . $enable),
          l($t_edit, $url . $edit),
          l($t_delete, $url . $delete),
        );
      }
    }
  }

  return array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#attributes' => array(),
    '#caption' => '',
    '#colgroups' => array(),
    '#sticky' => TRUE,
    '#empty' => t('There are no search servers defined yet.'),
  );
}

/**
 * Form callback showing a form for adding a server.
 */
function search_api_admin_add_server($form, &$form_state) {
  $class = empty($form_state['values']['class']) ? ''
      : $form_state['values']['class'];

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
    '#description' => t('Enter a display for the new server.'),
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

  foreach (search_api_get_service_info() as $id => $info) {
    $form['class']['#options'][$id] = $info['name'];

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
    $form['options'] = $service->configurationForm();
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
  $id = search_api_server_insert((object) $form_state['values']);
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
  if (!empty($action)) {
    if ($action == 'enable') {
      search_api_server_enable($server);
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

  return array();
}

/**
 * Edit a server's settings.
 *
 * @param SearchApiServiceInterface $server
 *   The server to edit.
 */
function search_api_admin_server_edit($form, &$form_state,
    SearchApiServiceInterface $server) {
  return array();
}

/**
 * Form callback showing a form for adding an index.
 */
function search_api_admin_add_index($form, &$form_state) {
  return array();
}

/**
 * Displays an index' details.
 *
 * @param stdClass $index
 *   The index to display.
 */
function search_api_admin_index_view(stdClass $index = NULL) {
  return array();
}

/**
 * Edit an index' settings.
 *
 * @param stdClass $index
 *   The index to edit.
 */
function search_api_admin_index_edit($form, &$form_state, stdClass $index) {
  // @todo If associated server was changed, also move/execute remembered tasks.
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
      'desired action. For details, see the logs.'), 'error');
  }

  $form_state['redirect'] = $action == 'delete'
      ? "admin/config/search/search_api"
      : "admin/config/search/search_api/$type/$id";
}
