<?php
// $Id$

/**
 * Search service class using the database for storing index information.
 */
class SearchApiDbService extends SearchApiAbstractService {

  protected $previousDb;
  protected $query_options;

  public function configurationForm() {
    if (empty($this->options)) {
      global $databases;
      foreach ($databases as $key => $targets) {
        foreach ($targets as $target => $info) {
          $options[$key]["$key:$target"] = "$key > $target";
        }
      }
      $form = array(
        'database' => array(
          '#type' => 'select',
          '#title' => t('Database'),
          '#description' => t('Select the database key and target to use for storing indexing information in. ' .
              'Cannot be changed after creation.'),
          '#options' => $options,
        ),
      );
    }
    else {
      $form = array(
        'database' => array(
          '#type' => 'item',
          '#title' => t('Database'),
          '#markup' => check_plain(str_replace(':', ' > ', $this->options['database'])),
        ),
      );
    }

    return $form;
  }

  public function preDelete() {
    if (empty($this->options['indexes'])) {
      return;
    }
    foreach ($this->options['indexes'] as $index) {
      foreach ($index as $field) {
        db_drop_table($field['table']);
      }
    }
  }

  public function addIndex(SearchApiIndex $index) {
    $this->options += array('indexes' => array());
    $indexes = &$this->options['indexes'];
    if (isset($indexes[$index->id])) {
      // Easiest and safest method to ensure all of the index' data is properly re-added.
      $this->removeIndex($index);
    }
    if (empty($index->options['fields'])) {
      // No fields, no work.
      $indexes[$index->id] = array();
      search_api_server_edit($this->id, array('options' => $this->options));
      return $this;
    }

    $prefix = 'search_api_db_' . $this->id . '_' . $index->id . '_';
    foreach ($this->renameFields($index->options['fields']) as $name => $field) {
      if (empty($field['indexed'])) {
        continue;
      }
      $this->createFieldTable($field, $prefix . $name);
      $indexes[$index->id][$name]['table'] = $prefix . $name;
      $indexes[$index->id][$name]['type']  = $field['type'];
      $indexes[$index->id][$name]['boost'] = $field['boost'];
      $indexes[$index->id][$name]['original_type']  = $field['original_type'];
    }

    search_api_server_edit($this->id, array('options' => $this->options));
  }

  /**
   * Helper method for renaming fields to be valid db identifiers.
   */
  protected function renameFields($fields) {
    $ret = array();
    foreach ($fields as $name => $field) {
      $new_name = $this->findFreeKey($name, $ret);
      $ret[$new_name] = $field;
    }
    return $ret;
  }

  /**
   * Helper method for finding an unused key that is a valid db identifier in an
   * array.
   */
  protected function findFreeKey($name, $array) {
    $subst = '';
    do {
      $subst .= '_';
      $new_name = preg_replace('/[^a-z0-9_]/i', $subst, $name);
    } while (isset($array[$new_name]));
    return $new_name;
  }

  /**
   * Helper method for creating the table for a field.
   */
  protected function createFieldTable($field, $name) {
    $type = $field['type'];
    $list = FALSE;
    while (substr($type, 0, 5) == 'list<') {
      $list = TRUE;
      $type = substr($type, 5, -1);
    }
    $table = array(
      'name' => $name,
      'module' => 'search_api_db',
      'fields' => array(
        'item_id' => array(
          'description' => 'The primary identifier of the entity.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ),
      ),
    );
    if ($type == 'text') {
      $table['fields']['token'] = array(
        'description' => 'The text of the indexed token.',
        'type' => 'varchar',
        'length' => 50,
      );
      $table['fields']['score'] = array(
        'description' => 'The score associated with this token.',
        'type' => 'float',
      );
      $table['primary key'] = array('item_id', 'token');
      $table['indexes']['token'] = array('token');
    }
    else {
      $table['fields']['value'] = $this->sqlType($type);
      $table['fields']['value'] += array('description' => "The field's value for this item.");
      if ($list) {
        $table['primary key'] = array('item_id', 'value');
      }
      else {
        $table['primary key'] = array('item_id');
      }
      $table['indexes']['value'] = array('value');
    }

    $set = $this->setDb();
    db_create_table($name, $table);
    if ($set) {
      $this->resetDb();
    }
  }

  protected function sqlType($type) {
    if (substr($type, 0, 5) == 'list<') {
      return $this->sqlType(substr($type, 5, -1));
    }
    switch ($type) {
      case 'string':
      case 'uri':
        return array('type' => 'varchar', 'length' => 255);
      case 'integer':
      case 'duration':
      case 'date': // 'datetime' sucks. This way, we just convert every input into a timestamp.
        return array('type' => 'int');
      case 'decimal':
        return array('type' => 'float');
      case 'boolean':
        return array('type' => 'int', 'size' => 'tiny');

      default:
        throw new SearchApiException(t('Unknown field type !type. Database search module might be out of sync with Search API.', array('!type' => $type)));
    }
  }

  public function fieldsUpdated(SearchApiIndex $index) {
    $fields = &$this->options['indexes'][$index->id];
    $new_fields = $index->options['fields'];
    foreach ($new_fields as $name => $field) {
      if (empty($field['indexed'])) {
        unset($new_fields[$name]);
      }
    }
    $new_fields = $this->renameFields($new_fields);

    $reindex = FALSE;
    $cleared = FALSE;
    $set = $this->setDb();
    foreach ($fields as $name => $field) {
      if (!isset($new_fields[$name])) {
        db_drop_table($field['table']);
        unset($fields[$name]);
        continue;
      }
      $old_type = $field['type'];
      $new_type = $new_fields[$name]['type'];
      if ($field['type'] != $new_fields[$name]['type']) {
        if ($old_type == 'text' || $new_type == 'text') {
          // A change in fulltext status necessitates completely clearing the index.
          $reindex = TRUE;
          if (!$cleared) {
            $cleared = TRUE;
            $this->deleteItems('all', $index);
          }
          db_drop_table($field['table']);
          $this->createFieldTable($new_fields[$name], $field['table']);
        }
        elseif ($this->sqlType($old_type) != $this->sqlType($new_type)) {
          // There is a change in SQL type. We don't have to clear the index, since types can be converted.
          db_change_field($field['table'], 'value', 'value', $this->sqlType($new_type) + array('description' => "The field's value for this item."));
          $reindex = TRUE;
        }
        elseif ($old_type == 'date' || $new_type == 'date') {
          // Even though the SQL type stays the same, we have to reindex since conversion rules change.
          $reindex = TRUE;
        }
      }
      if (!$reindex && $new_type == 'text' && $field['boost'] != $new_fields[$name]['boost']) {
        $multiplier = $new_fields[$name]['boost'] / $field['boost'];
        db_update($field['table'], $this->query_options)
          ->expression('score', 'score * :mult', array(':mult' => $multiplier))
          ->execute();
      }
      unset($new_fields[$name]);
    }
    // These are new fields that were previously not indexed.
    foreach ($new_fields as $name => $field) {
      $reindex = TRUE;
      $this->createFieldTable($field, $prefix . $name);
      $fields[$name]['table'] = $prefix . $name;
      $fields[$name]['type']  = $field['type'];
      $fields[$name]['boost'] = $field['boost'];
      $indexes[$index->id][$name]['original_type']  = $field['original_type'];
    }
    if ($set) {
      $this->resetDb();
    }

    search_api_server_edit($this->id, array('options' => $this->options));
    return $reindex;
  }

  public function removeIndex($index) {
    $id = is_object($index) ? $index->id : $index;
    if (!isset($this->options['indexes'][$id])) {
      return;
    }
    $set = $this->setDb();
    foreach ($this->options['indexes'][$id] as $field) {
      db_drop_table($field['table']);
    }
    if ($set) {
      $this->resetDb();
    }
    unset($this->options['indexes'][$id]);
    search_api_server_edit($this->id, array('options' => $this->options));
  }

  public function indexItems(SearchApiIndex $index, array $items) {
    $indexed = array();
    foreach ($items as $id => $item) {
      if ($this->indexItem($index, $id, $item)) {
        $indexed[] = $id;
      }
    }
    return $indexed;
  }

  protected function indexItem(SearchApiIndex $index, $id, array $item) {
    if (empty($this->options['indexes'][$index->id])) {
      throw new SearchApiException(t('No field settings for index with id !id.', array('!id' => $index->id)));
    }
    $fields = $this->options['indexes'][$index->id];
    foreach ($item as $name => $field) {
      $type = $field['type'];
      $value = $this->convert($field['value'], $type, $field['original_type']);
      if (search_api_is_text_type($type, array('text', 'tokens'))) {

      }
      else {

      }
    }
  }

  protected function convert($value, $type, $original_type) {
    if (substr($type, 0, 5) == 'list<') {
      $type = substr($type, 5, -1);
      $ret = array();
      foreach ($value as $v) {
        $ret = array_merge($ret, $this->convert($v, $type));
      }
      return $ret;
    }
    switch ($type) {
      case 'text':
        $ret = array();
        foreach (preg_split('', $value) as $v) {
          $ret[] = array(
            'value' => $v,
            'score' => 1.0,
          );
        }
        return $ret;

      case 'tokens':
        return $value;

      case 'string':
      case 'uri':
        // For non-dates, PHP can handle this well enough
        if ($original_type == 'date') {
          return date('%c', $value);
        }
        return $value;

      case 'integer':
      case 'duration':
      case 'decimal':
        return 0 + $value;

      case 'boolean':
        return $value ? 0 : 1;

      case 'date':
        if (preg_match('/^\d*$/', $value)) {
          return 0 + $value;
        }
        return strtotime($value);

      default:
        throw new SearchApiException(t('Unknown field type !type. Database search module might be out of sync with Search API.', array('!type' => $type)));
    }
  }

  public function deleteItems($ids, SearchApiIndex $index = NULL) {
    if (!$index) {
      if (empty($this->options['indexes'])) {
        return;
      }
      $set = $this->setDb();
      foreach ($this->options['indexes'] as $index) {
        foreach ($index as $fields) {
          foreach ($fields as $field) {
            db_delete($field['table'], $this->query_options)->execute();
          }
        }
      }
      if ($set) {
        $this->resetDb();
      }
      return;
    }

    if (empty($this->options['indexes'][$index->id])) {
      return;
    }
    $set = $this->setDb();
    foreach ($this->options['indexes'][$index->id] as $field) {
      $query = db_delete($field['table'], $this->query_options);
      if (is_array($ids)) {
        $query->condition('item_id', $ids, 'IN');
      }
      $query->execute();
    }
    if ($set) {
      $this->resetDb();
    }
  }

  public function search(SearchApiQueryInterface $query) {

  }

  /**
   * Helper method for setting the database to the one selected by the user.
   */
  protected function setDb() {
    if (!isset($this->previousDb)) {
      list($key, $target) = explode(':', $this->options['database'], 2);
      $this->previousDb = db_set_active($key);
      if (!isset($this->query_options)) {
        $this->query_options = array('target' => $target);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper method for resetting the original database.
   */
  protected function resetDb() {
    if (isset($this->previousDb)) {
      db_set_active($this->previousDb);
      $this->previousDb = NULL;
      return TRUE;
    }
    return FALSE;
  }

}
