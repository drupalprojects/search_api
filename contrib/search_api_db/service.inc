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
        'min_chars' => array(
          '#type' => 'select',
          '#title' => t('Minimum word length'),
          '#description' => t('The minimum number of characters a word must consist of to be indexed.'),
          '#options' => drupal_map_assoc(array(1, 2, 3, 4, 5, 6)),
        ),
      );
    }
    else {
      $form = array(
        'database' => array(
          '#type' => 'value',
          '#title' => t('Database'), // Slight hack for the "View server" page.
          '#value' => $this->options['database'],
        ),
        'database_text' => array(
          '#type' => 'item',
          '#title' => t('Database'),
          '#markup' => check_plain(str_replace(':', ' > ', $this->options['database'])),
        ),
        'min_chars' => array(
          '#type' => 'select',
          '#title' => t('Minimum word length'),
          '#description' => t('The minimum number of characters a word must consist of to be indexed.'),
          '#options' => drupal_map_assoc(array(1, 2, 3, 4, 5, 6)),
          '#default_value' => $this->options['min_chars'],
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
    foreach ($index->options['fields'] as $name => $field) {
      if (empty($field['indexed'])) {
        continue;
      }
      $table = $this->findFreeTable($prefix, $name);
      $this->createFieldTable($field, $table);
      $indexes[$index->id][$name]['table'] = $table;
      $indexes[$index->id][$name]['type']  = $field['type'];
      $indexes[$index->id][$name]['boost'] = $field['boost'];
    }

    search_api_server_edit($this->id, array('options' => $this->options));
  }

  /**
   * Helper method for finding table names for fields.
   */
  protected function findFreeTable($prefix, $name) {
    $subst = '';
    do {
      $subst .= '_';
      $ret = $prefix . preg_replace('/[^a-z0-9_]/i', $subst, $name);
      if (strlen($subst) > 10) {
        throw new SearchApiException(t('Possible infinite loop detected because of existing table !table. The initial cause might be stale data due to an unsuccessful update. Please remove the corresponding index from the server and afterwards add it again.', array('!table' => $prefix . preg_replace('/[^a-z0-9_]/i', '_', $name))));
      }
    } while (db_table_exists($ret));
    return $ret;
  }

  /**
   * Helper method for creating the table for a field.
   */
  protected function createFieldTable($field, $name) {
    $type = $field['type'];
    if ($list = search_api_is_list_type($type)) {
      $type = search_api_extract_inner_type($type);
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
      $table['fields']['word'] = array(
        'description' => 'The text of the indexed token.',
        'type' => 'varchar',
        'length' => 50,
      );
      $table['fields']['score'] = array(
        'description' => 'The score associated with this token.',
        'type' => 'float',
      );
      $table['primary key'] = array('item_id', 'word');
      $table['indexes']['word'] = array('word');
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
    $type = search_api_extract_inner_type($type);
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
    $new_fields = $new_fields;

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
    $prefix = 'search_api_db_' . $this->id . '_' . $index->id . '_';
    // These are new fields that were previously not indexed.
    foreach ($new_fields as $name => $field) {
      $reindex = TRUE;
      $table = $this->findFreeTable($prefix, $name);
      $this->createFieldTable($field, $table);
      $fields[$name]['table'] = $table;
      $fields[$name]['type']  = $field['type'];
      $fields[$name]['boost'] = $field['boost'];
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
    if (empty($this->options['indexes'][$index->id])) {
      throw new SearchApiException(t('No field settings for index with id !id.', array('!id' => $index->id)));
    }
    $indexed = array();
    $set = $this->setDb();
    foreach ($items as $id => $item) {
      try {
        if ($this->indexItem($index, $id, $item)) {
          $indexed[] = $id;
        }
      }
      catch (Exception $e) {
        // We just log the error, hoping we can index the other items.
        watchdog('search api', $e->getMessage(), NULL, WATCHDOG_WARNING);
      }
    }
    if ($set) {
      $this->resetDb();
    }
    return $indexed;
  }

  protected function indexItem(SearchApiIndex $index, $id, array $item) {
    $fields = $this->options['indexes'][$index->id];
    $txn = db_transaction('search_api_indexing', $this->query_options);
    $item = $item;
    try {
      foreach ($item as $name => $field) {
        $table = $fields[$name]['table'];
        $boost = $fields[$name]['boost'];
        db_delete($table, $this->query_options)
            ->condition('item_id', $id)
            ->execute();
        $type = $field['type'];
        $value = $this->convert($field['value'], $type, $field['original_type']);

        if (search_api_is_text_type($type, array('text', 'tokens'))) {
          $words = array();
          foreach ($value as $token) {
            if (drupal_strlen($token['value']) < $this->options['min_chars']) {
              continue;
            }
            $token['value'] = drupal_strtolower($token['value']);
            if (!isset($words[$token['value']])) {
              $words[$token['value']] = $token;
            }
            else {
              $words[$token['value']]['score'] += $token['score'];
            }
          }
          if ($words) {
            $query = db_insert($table, $this->query_options)
              ->fields(array('item_id', 'word', 'score'));
            foreach ($words as $word) {
              $query->values(array(
                'item_id' => $id,
                'word'    => $word['value'],
                'score'   => $word['score'] * $boost,
              ));
            }
            $query->execute();
          }
        }
        else {
          db_insert($table, $this->query_options)
            ->fields(array(
              'item_id' => $id,
              'value'   => $value,
            ))
            ->execute();
        }
      }
    }
    catch (Exception $e) {
      $txn->rollback();
      throw $e;
    }
    return TRUE;
  }

  protected function convert($value, $type, $original_type) {
    if (search_api_is_list_type($type)) {
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
        foreach (preg_split('/\s+/', $value, PREG_SPLIT_NO_EMPTY) as $v) {
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
            db_truncate($field['table'], $this->query_options)->execute();
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
      if (is_array($ids)) {
        db_delete($field['table'], $this->query_options)
          ->condition('item_id', $ids, 'IN')
          ->execute();
      }
      else {
        db_truncate($field['table'], $this->query_options)->execute();
      }
    }
    if ($set) {
      $this->resetDb();
    }
  }

  public function search(SearchApiQueryInterface $query) {
    $index = $query->getIndex();
    if (empty($this->options['indexes'][$index->id])) {
      throw new SearchApiException();
    }
    $fields = $this->options['indexes'][$index->id];
    $tables = array();
    $keys = &$query->getKeys();
    $keys = $this->prepareKeys($keys);
    if ($keys) {
      $fulltext_fields = $query->getFields();
      if (!isset($fulltext_fields)) {
        $fulltext_fields = array();
        foreach ($fields as $name => $field) {
          $fulltext_fields[] = $name;
        }
      }
      if ($fulltext_fields) {
        $_fulltext_fields = $fulltext_fields;
        $fulltext_fields = array();
        foreach ($_fulltext_fields as $name) {
          if (!isset($fields[$name])) {
            throw new SearchApiException(t('Unknown field !field specified as search target.', array('!field' => $name)));
          }
          if (!search_api_is_text_type($fields[$name]['type'])) {
            throw new SearchApiException(t('Cannot perform fulltext search on field !field of type !type.', array('!field' => $name, '!type' => $fields[$name]['type'])));
          }
          $fulltext_fields[$name] = $fields[$name];
        }

        $db_query = $this->createKeysQuery($keys, $fulltext_fields);
      }
      else {
        watchdog('search api', t('Search keys are given but no fulltext fields are defined.'), NULL, WATCHDOG_WARNING);
      }
    }
  }

  /**
   * Helper method for removing unnecessary nested expressions from keys.
   */
  protected function prepareKeys($keys) {
    if (is_string($keys)) {
      $keys = $this->splitKeys($keys);
      return is_array($keys) ? $this->eliminateDuplicates($keys) : $keys;
    }
    elseif (!$keys) {
      return NULL;
    }
    $keys = $this->eliminateDuplicates($this->splitKeys($keys));
    $conj = $keys['#conjunction'];
    $neg = !empty($keys['#negation']);
    foreach ($keys as $i => &$nested) {
      if (is_array($nested)) {
        $nested = $this->prepareKeys($nested);
        if (($count = count($nested)) <= 2) {
          if ($count < 2 || isset($nested['#negation'])) {
            unset($keys[$i]);
          }
          else {
            unset($nested['#conjunction']);
            $nested = array_shift($nested);
            $keys[$i] = $nested;
          }
        }
        if ($neg == !empty($nested['#negation'])) {
          if ($nested['#conjunction'] == $conj) {
            unset($nested['#conjunction'], $nested['#negation']);
            foreach ($nested as $renested) {
              $keys[] = $renested;
            }
            unset($keys[$i]);
          }
        }
      }
    }
    return $keys;
  }

  /**
   * Helper method for splitting keys.
   */
  protected function splitKeys($keys) {
    if (is_string($keys)) {
      $keys = drupal_strtolower(trim($keys));
      if (drupal_strlen($keys) < $this->options['min_chars']) {
        $this->ignored[$keys] = $keys;
        return NULL;
      }
      $words = preg_split('/\s+/', $keys);
      if (count($words) > 1) {
        $keys = $this->splitKeys($words);
        $keys['#conjunction'] = 'AND';
      }
      return $keys;
    }
    foreach ($keys as $i => $key) {
      if (element_child($i)) {
        $keys[$i] = $this->splitKeys($key);
      }
    }
    return array_filter($keys);
  }

  /**
   * Helper method for eliminating duplicates from the search keys.
   */
  protected function eliminateDuplicates($keys, &$words = array()) {
    foreach ($keys as $i => $word) {
      if (!element_child($i)) {
        continue;
      }
      if (is_string($word)) {
        if (isset($words[$word])) {
          unset($keys[$i]);
        }
        else {
          $words[$word] = TRUE;
        }
      }
      else {
        $keys[$i] = $this->eliminateDuplicates($word, $words);
      }
    }
    return $keys;
  }

  /**
   * Helper method for creating a SELECT query for given search keys.
   */
  protected function createKeysQuery($keys, array $fields) {
    if (!is_array($keys)) {
      $keys = array(
        '#conjunction' => 'AND',
        $keys,
      );
    }

    return NULL;
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
