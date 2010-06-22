<?php
// $Id$

/**
 * Page callback that shows an overview of defined servers and indexes.
 */
function search_api_admin_overview() {
  return array();
}

/**
 * Form callback showing a form for adding a server.
 */
function search_api_admin_add_server($form, &$form_state) {
  return array();
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
function search_api_admin_server_view(SearchApiServiceInterface $server) {
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
 * Helper function for displaying a generic confirmation form.
 */
function search_api_admin_confirm($form, &$form_state, $type, $action, $subj) {
  return array();
}
