<?php
// $Id$

/**
 * Page callback that shows an overview of defined servers and indexes.
 */
function search_api_admin_overview() {
  $header = array(
    array('data' => t('Type'), 'colspan' => 2),
    array('data' => t('Name'), 'field' => 'name', 'sort' => 'asc'),
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
  $t_server = t('Server');
  $t_index = t('Index');
  $t_enabled = '<strong>' . t('enabled') . '</strong>';
  $t_disabled = t('disabled');
  $t_enable = t('enable');
  $t_disable = t('disable');
  $t_edit = t('edit');
  $t_delete = t('delete');
  $pre = 'admin/config/search/search_api/server/';
  $pre_index = 'admin/config/search/search_api/index/';
  $en = '/enable';
  $ed = '/edit';
  $del = '/delete';
  foreach ($servers as $server) {
    $rows[] = array(
      array('data' => $t_server, 'colspan' => 2),
      $server->name,
      $server->enabled ? $t_enabled : $t_disabled,
      l($server->enabled ? $t_enable : $t_disable, $pre . $server->id . $en),
      l($t_edit, $pre . $server->id . $ed),
      l($t_delete, $pre . $server->id . $del),
    );
    if (!empty($indexes[$server->id])) {
      foreach ($indexes[$server->id] as $index) {
        $rows[] = array(
          '–',
          $t_index,
          $index->name,
          $index->enabled ? $t_enabled : $t_disabled,
          l($index->enabled ? $t_enable : $t_disable,
              $pre_index . $index->id . $en),
          l($t_edit, $pre_index . $index->id . $ed),
          l($t_delete, $pre_index . $index->id . $del),
        );
      }
    }
  }

  return theme_table(array(
    '#theme' => 'table',
    'header' => $header,
    'rows' => $rows,
    'attributes' => array(),
    'caption' => '',
    'colgroups' => array(),
    'sticky' => TRUE,
    'empty' => t('There are no search servers defined yet.'),
  ));
}

/**
 * Form callback showing a form for adding a server.
 */
function search_api_admin_add_server($form, &$form_state) {
  $submit = !empty($form_state['input']);
  $form['#attached']['js'] = array(
    drupal_get_path('module', 'search_api') . '/search_api.admin.js',
  );

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
    '#attributes' => array('class' => array('search_api-service-select')),
    '#required' => TRUE,
  );

  foreach (search_api_get_service_info() as $id => $info) {
    if ($submit && $form_state['input']['class'] != $id) {
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
    $form['class']['#options'][$id] = $info['name'];
    $key = 'class-' . $id;
    $form[$key] = $service->configurationForm();
    $form[$key]['#type'] = 'fieldset';
    $form[$key]['#tree'] = TRUE;
    $form[$key]['#collapsible'] = TRUE;
    $form[$key]['#title'] = $info['name'];
    $form[$key]['#description'] = $info['description'];
    $form[$key]['#attributes'] = array('class' => array(
        'search_api-service-settings',
        'search_api-service-' . $id,
      ),
    );
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Create server'),
  );

  return $form;
}

/**
 * Form submit callback for adding a server.
 */
function search_api_admin_add_server_submit($form, &$form_state) {
  $values = $form_state['values'];
  $values['options'] = $values['class-' . $values['class']];
  $id = search_api_server_insert((object) $values);
  if ($id) {
    $form_state['redirect'] = 'admin/config/search/search_api/server/' . $id;
  }
}

/**
 * Form callback showing a form for adding an index.
 */
function search_api_admin_add_index($form, &$form_state) {
  return array();
}

/**
 * Displays a server's details.
 *
 * @param $server
 *   The server to display.
 */
function search_api_admin_server_view(SearchApiServiceInterface $server = NULL) {
  return array();
}

/**
 * Edit a server's settings.
 *
 * @param $server
 *   The server to edit.
 */
function search_api_admin_server_edit($form, &$form_state,
    SearchApiServiceInterface $server) {
  return array();
}

/**
 * Displays an index' details.
 *
 * @param $index
 *   The index to display.
 */
function search_api_admin_index_view(stdClass $index) {
  return array();
}

/**
 * Edit an index' settings.
 *
 * @param $index
 *   The index to edit.
 */
function search_api_admin_index_edit($form, &$form_state, stdClass $index) {
  return array();
}


/**
 * Helper function for displaying a generic confirmation form.
 */
function search_api_admin_confirm($form, &$form_state, $type, $action, $subj) {
  return array();
}
