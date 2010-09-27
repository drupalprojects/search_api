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
  if (!$index->server() || !$index->server()->supportsFeature('search_api_facets')) {
    // We can't offer any facets, but maybe the user can delete old facet data.
    drupal_set_message(t("The server this index currently lies on doesn't support facets. " .
        'To use facets, you will have to move this index to a server supporting this feature.'), 'error');
    if (db_query('SELECT COUNT(1) FROM {search_api_facet} WHERE index_id = :id', array(':id' => $index->id))->fetchField()) {
      $form['index'] = array(
        '#type' => 'value',
        '#value' => $index->id,
      );
      $form['description'] = array(
        '#type' => 'item',
        '#title' => t('Delete facet settings'),
        '#description' => t("If you won't use facets with this index anymore, you can delete all facet data associated with it. " .
            "This will delete all settings for facets on this index. Facets on other indexes won't be influenced."),
      );
      $form['button'] = array(
        '#type' => 'submit',
        '#value' => t('Delete facet settings'),
        '#submit' => array('search_api_facets_index_select_submit_delete'),
      );
    }
    return $form;
  }

  $form['description'] = array(
    '#type' => 'item',
    '#title' => t('Select the available facet blocks'),
    '#description' => t('<p>All enabled blocks will be shown, along with their names, on the block administration page. ' .
        'For your own convenience, choose unique names. ' .
        'The names will also be displayed to normal users, unless manually changed in the block settings. ' .
        'By cloning blocks, you can have multiple facet blocks with seperate configurations for a single index field.</p>' .
        "<p>When you won't be using a disabled facet anymore, you can delete all its settings permanently, reducing database size. " .
        "This won't influence other facets. You can enable it again later, but without the previously stored settings.</p>" .
        "<p>Note that, even though facets for fulltext fields are available, they won't make much sense in most scenarios.</p>"),
  );
  $form['facets'] = array(
    '#tree' => TRUE,
    '#theme' => 'search_api_facets_form_table',
    '#table_header' => array(
      t('Enabled'),
      t('Field'),
      t('Type'),
      t('Name'),
      t('Clone block'),
      t('Delete data'),
    ),
    '#table_empty' => t('There are currently no fields for which facets can be displayed.'),
  );

  if (!empty($index->options['fields'])) {
    $facets = array();
    if ($disabled = empty($index->enabled)) {
      drupal_set_message('Since this index is at the moment disabled, no facet blocks can be activated.', 'warning');
    }
    foreach (search_api_facet_load(array('index_id' => $index->id)) as $facet) {
      $facets[$facet->field][] = $facet;
    }
    $types = search_api_field_types();
    foreach ($index->options['fields'] as $key => $field) {
      if (!$field['indexed']) {
        continue;
      }
      $type = search_api_extract_inner_type($field['type']);
      $type = isset($types[$type]) ? $types[$type] : $type;
      if (empty($facets[$key])) {
        $facets[$key][] = (object) array(
          'index_id' => $index->id,
          'field' => $key,
          'name' => t('!index: Filter on !field', array('!field' => $field['name'], '!index' => $index->name)),
          'enabled' => FALSE,
          'options' => array(),
        );
      }
      foreach ($facets[$key] as $i => $facet) {
        $k = $i ? "$key-$i" : $key;
        $form['facets'][$k]['facet'] = array(
          '#type' => 'value',
          '#value' => $facet,
        );
        $form['facets'][$k]['enabled'] = array(
          '#type' => 'checkbox',
          '#default_value' => $facet->enabled,
          '#disabled' => $disabled,
        );
        $form['facets'][$k]['field'] = array(
          '#markup' => $field['name'],
        );
        $form['facets'][$k]['type'] = array(
          '#markup' => $type,
        );
        $form['facets'][$k]['name'] = array(
          '#type' => 'textfield',
          '#maxlength' => max(strlen($facet->name), 80),
          '#default_value' => $facet->name,
        );
        $form['facets'][$k]['clone'] = array(
          '#type' => 'checkbox',
          '#default_value' => FALSE,
        );
        if ($facet->enabled || empty($facet->delta)) {
          $form['facets'][$k]['delete'] = array(
            '#markup' => '&nbsp;',
          );
        }
        else {
          $form['facets'][$k]['delete'] = array(
            '#type' => 'checkbox',
            '#default_value' => FALSE,
          );
        }
      }
    }
  }

  $form['index'] = array(
    '#type' => 'value',
    '#value' => $index,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save settings'),
  );

  return $form;
}

/**
 * Validation callback for search_api_facets_index_select().
 */
function search_api_facets_index_select_validate(array $form, array &$form_state) {
  $warn = FALSE;
  foreach ($form_state['values']['facets'] as $key => $v) {
    if (!empty($v['facet']->delta) || $v['enabled']) {
      // -> This setting will be stored.
      if (!$v['name']) {
        form_set_error("facets][$key][name", t("You can't set an empty name."));
      }
      elseif (strlen($v['name']) > 80) {
        form_set_error("facets][$key][name", t('Names cannot be longer than 80 characters, but "@name" is @count characters long.',
            array('@name' => $v['name'], '@count' => strlen($v['name']))));
      }
    }
    elseif ($v['facet']->name != $v['name']) {
      $warn = TRUE;
    }
  }
  if ($warn) {
    drupal_set_message(t('Note that changed names of facet blocks that are disabled and have no settings yet, are not saved.'), 'warning');
  }
}

/**
 * Submit callback for search_api_facets_index_select().
 */
function search_api_facets_index_select_submit(array $form, array &$form_state) {
  $index = $form_state['values']['index'];
  $count = 0;
  $clone_count = 0;
  $delete_count = 0;

  $counts = array();
  foreach ($form_state['values']['facets'] as $key => $v) {
    $field = $v['facet']->field;
    $c = $counts[$field] = (isset($counts[$field]) ? $counts[$field] + 1 : 1);
    if (empty($v['enabled']) && empty($v['facet']->delta)) {
      // Field is not stored, what use would cloning or deleting be?
      continue;
    }
    if (empty($v['enabled']) && empty($v['clone'])) {
      // This is the only case in which a "delete" makes sense.
      if (!empty($v['delete'])) {
        if (search_api_facet_delete($v['facet']->delta)) {
          ++$delete_count;
        }
        continue;
      }
    }
    $f = $v['facet'];
    $update = $f->enabled != $v['enabled'] || $f->name != $v['name'];
    if ($update) {
      $f = clone $f;
      $f->enabled = $v['enabled'];
      $f->name = $v['name'];
      search_api_facet_save($f);
      ++$count;
    }
    if ($v['clone']) {
      $f = $v['facet'];
      unset($f->delta);
      $f->name = t('!index: Filter on !field #!num',
          array('!field' => $index->options['fields'][$f->field]['name'], '!index' => $index->name, '!num' => $c + 1));
      search_api_facet_save($f);
      ++$clone_count;
    }
  }

  if ($clone_count) {
    drupal_set_message(format_plural($clone_count, '1 facet block was successfully cloned.', '@count facet blocks were successfully cloned.'));
  }
  if ($delete_count) {
    drupal_set_message(format_plural($delete_count, 'The settings of 1 facet block were successfully deleted.', 'The settings of @count facet blocks were successfully deleted.'));
  }
  if ($count) {
    drupal_set_message(format_plural($count, '1 facet block was successfully updated.', '@count facet blocks were successfully updated.'));
  }
  if (!($count + $clone_count + $delete_count)) {
    drupal_set_message(t('No values were changed.'));
  }
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
