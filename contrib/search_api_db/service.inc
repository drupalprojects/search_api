<?php
// $Id$

/**
 * Search service class using the database for storing index information.
 */
class SearchApiDbService extends SearchApiAbstractService {

  protected $previous_db;
  protected $query_options = array();
  protected $ignored = array();
  protected $warnings = array();

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
          '#required' => TRUE,
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

  public function postUpdate(array $fields) {
    return isset($fields['options']) && $fields['options'] != $this->options;
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
    if (empty($index->options['fields']) || !is_array($index->options['fields'])) {
      // No fields, no work.
      $indexes[$index->id] = array();
      search_api_server_edit($this->id, array('options' => $this->options));
      return $this;
    }

    $prefix = 'search_api_db_' . $this->id . '_' . $index->id . '_';
    $indexes[$index->id] = array();
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
        'not null' => TRUE,
      );
      $table['fields']['score'] = array(
        'description' => 'The score associated with this token.',
        'type' => 'float',
        'not null' => TRUE,
      );
      $table['primary key'] = array('item_id', 'word');
      $table['indexes']['word'] = array(array('word', 10));
    }
    else {
      $table['fields']['value'] = $this->sqlType($type);
      $table['fields']['value'] += array('description' => "The field's value for this item.");
      if ($list) {
        $table['fields']['value']['not null'] = TRUE;
        $table['primary key'] = array('item_id', 'value');
      }
      else {
        $table['primary key'] = array('item_id');
      }
      $table['indexes']['value'] = $table['fields']['value'] == 'varchar' ? array(array('value', 10)) : array('value');
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
            // Taken from core search to reflect less importance of words later in the text.
            // Focus is a decaying value in terms of the amount of unique words up to this point.
            // From 100 words and more, it decays, to e.g. 0.5 at 500 words and 0.3 at 1000 words.
            $focus = min(1, .01 + 3.5 / (2 + count($words) * .015));

            $value = &$token['value'];
            if (is_numeric($value)) {
              $value = ltrim($value, '-0');
            }
            elseif (drupal_strlen($value) < $this->options['min_chars']) {
              continue;
            }
            $value = drupal_strtolower($value);
            $token['score'] *= $focus;
            if (!isset($words[$value])) {
              $words[$value] = $token;
            }
            else {
              $words[$value]['score'] += $token['score'];
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
        $v = $this->convert($v, $type);
        $ret = array_merge($ret, is_array($v) ? $v : array($v));
      }
      return $ret;
    }
    switch ($type) {
      case 'text':
        $ret = array();
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) as $v) {
          if ($v) {
            $ret[] = array(
              'value' => $v,
              'score' => 1.0,
            );
          }
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
        if (is_numeric($value) || !$value) {
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
    $time_method_called = microtime(TRUE);
    $set = $this->setDb();
    $index = $query->getIndex();
    if (empty($this->options['indexes'][$index->id])) {
      throw new SearchApiException(t('Unknown index !id.', array('!id' => $index->id)));
    }
    $fields = $this->options['indexes'][$index->id];

    $keys = &$query->getKeys();
    $keys_set = (boolean) $keys;
    $keys = $this->prepareKeys($keys);
    if ($keys && !(is_array($keys) && count($keys) == 1)) {
      $fulltext_fields = $query->getFields();
      if (!isset($fulltext_fields)) {
        $fulltext_fields = array();
        foreach ($fields as $name => $field) {
          if (search_api_is_text_type($field['type'])) {
            $fulltext_fields[] = $name;
          }
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

        $db_query = $this->createKeysQuery($keys, $fulltext_fields, $fields);
        if (is_array($keys) && !empty($keys['#negation'])) {
          $db_query->addExpression(':score', 'score', array(':score' => 1));
        }
      }
      else {
        $msg = t('Search keys are given but no fulltext fields are defined.');
        watchdog('search api', $msg, NULL, WATCHDOG_WARNING);
        $this->warnings[$msg] = 1;
      }
    }
    elseif ($keys_set) {
      $msg = t('No valid search keys were present in the query.');
      $this->warnings[$msg] = 1;
    }

    $filter = $query->getFilter();
    if ($filter->getFilters()) {
      if (!isset($db_query)) {
        $db_query = db_select($fields['search-api-language']['table'], 't', $this->query_options);
        $db_query->addField('t', 'item_id', 'item_id');
        $db_query->addExpression(':score', 'score', array(':score' => 1));
      }
      if (isset($db_query)) {
        $condition = $this->createFilterCondition($filter, $fields, $db_query);
        if ($condition) {
          $db_query->condition($condition);
        }
      }
    }

    if (!isset($db_query)) {
      $msg = t('No valid search keys or filters given.');
      $this->warnings[$msg] = 1;
      $db_query = db_select($fields['search-api-language']['table'], 't', $this->query_options);
      $db_query->addField('t', 'item_id', 'item_id');
      $db_query->addExpression(':score', 'score', array(':score' => 1));
    }

    $time_processing_done = microtime(TRUE);
    $results = array();
    $count_query = $db_query->countQuery();
    $results['result count'] = $count_query->execute()->fetchField();

    if ($results['result count']) {
      $query_options = $query->getOptions();
      if (isset($query_options['offset']) || isset($query_options['limit'])) {
        $offset = isset($query_options['offset']) ? $query_options['offset'] : 0;
        $limit = isset($query_options['limit']) ? $query_options['limit'] : 1000000;
        $db_query->range($offset, $limit);
      }

      $sort = $query->getSort();
      if ($sort) {
        foreach ($sort as $field_name => $order) {
          if ($order != 'ASC' && $order != 'DESC') {
            $msg = t('Unknown sort order !order. Assuming "ASC".', array('!order' => $order));
            $this->warnings[$msg] = $msg;
            $order = 'ASC';
          }
          if ($field_name == 'search-api-relevance') {
            $db_query->orderBy('score', $order);
            continue;
          }
          if ($field_name == 'search-api-id') {
            $db_query->orderBy('item_id', $order);
            continue;
          }
          if (!isset($fields[$field_name])) {
            throw new SearchApiException(t('Trying to sort on unknown field !field.', array('!field' => $field_name)));
          }
          $field = $fields[$field_name];
          if (search_api_is_list_type($field['type'])) {
            throw new SearchApiException(t('Cannot sort on field !field of a list type.', array('!field' => $field_name)));
          }
          if (search_api_is_text_type($field['type'])) {
            throw new SearchApiException(t('Cannot sort on fulltext field !field.', array('!field' => $field_name)));
          }
          $alias = $this->getTableAlias($field, $db_query);
          $db_query->orderBy($alias . '.value', $order);
        }
      }
      else {
        $db_query->orderBy('score', 'DESC');
      }

      $result = $db_query->execute();
      $time_queries_done = microtime(TRUE);

      foreach ($result as $row) {
        $results['results'][$row->item_id] = array(
          'id' => $row->item_id,
          'score' => $row->score,
        );
      }
    }
    else {
      $time_queries_done = microtime(TRUE);
      $results['results'] = array();
    }

    $results['warnings'] = array_keys($this->warnings);
    $results['ignored'] = array_keys($this->ignored);

    if ($set) {
      $this->resetDb();
    }

    $time_end = microtime(TRUE);
    $results['performance'] = array(
      'complete' => $time_end - $time_method_called,
      'preprocessing' => $time_processing_done - $time_method_called,
      'execution' => $time_queries_done - $time_processing_done,
      'postprocessing' => $time_end - $time_queries_done,
    );

    return $results;
  }

  /**
   * Helper method for removing unnecessary nested expressions from keys.
   */
  protected function prepareKeys($keys) {
    if (is_scalar($keys)) {
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
    $keys = array_filter($keys);
    if (($count = count($keys)) <= 2) {
      if ($count < 2 || isset($keys['#negation'])) {
        $keys = NULL;
      }
      else {
        unset($keys['#conjunction']);
        $keys = array_shift($keys);
      }
    }
    return $keys;
  }

  /**
   * Helper method for splitting keys.
   */
  protected function splitKeys($keys) {
    if (is_scalar($keys)) {
      $proc = drupal_strtolower(trim($keys));
      if (is_numeric($proc)) {
        return ltrim($proc, '-0');
      }
      elseif (drupal_strlen($proc) < $this->options['min_chars']) {
        $this->ignored[$keys] = 1;
        return NULL;
      }
      $words = preg_split('/[^\p{L}\p{N}]+/u', $proc, -1, PREG_SPLIT_NO_EMPTY);
      if (count($words) > 1) {
        $proc = $this->splitKeys($words);
        $proc['#conjunction'] = 'AND';
      }
      return $proc;
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
      if (is_scalar($word)) {
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
   *
   * @return SelectQueryInterface
   *   A SELECT query returning item_id and score (or only item_id, if
   *   $keys['#negation'] is set).
   */
  protected function createKeysQuery($keys, array $fields, array $all_fields) {
    if (!is_array($keys)) {
      $keys = array(
        '#conjunction' => 'AND',
        $keys,
      );
    }

    $or = db_or();
    $neg = !empty($keys['#negation']);
    $conj = $keys['#conjunction'];
    $words = array();
    $nested = array();
    $negated = array();
    $db_query = NULL;
    $mul_words = FALSE;
    $not_nested = FALSE; // If the query will nest UNIONed subqueries or just leave them that way.

    foreach ($keys as $i => $key) {
      if (!element_child($i)) {
        continue;
      }
      if (is_scalar($key)) {
        $words[] = $key;
      }
      elseif (empty($key['#negation'])) {
        if ($neg) {
          $key['#negation'] = TRUE; // If this query is negated, we also only need item_ids from subqueries.
        }
        $nested[] = $key;
      }
      else {
        $negated[] = $key;
      }
    }
    $subs = count($words) + count($nested);
    $not_nested = ($subs <= 1 && count($fields) == 1) || ($neg && $conj == 'OR' && !$negated);

    if ($words) {
      if (count($words) > 1) {
        $mul_words = TRUE;
        foreach ($words as $word) {
          $or->condition('word', $word);
        }
      }
      else {
        $word = array_shift($words);
      }
      foreach ($fields as $name => $field) {
        $table = $field['table'];
        $query = db_select($table, 't', $this->query_options);
        if ($neg) {
          $query->fields('t', array('item_id'));
        }
        elseif ($not_nested) {
          $query->fields('t', array('item_id', 'score'));
        }
        else {
          $query->fields('t');
        }
        if ($mul_words) {
          $query->condition($or);
        }
        else {
          $query->condition('word', $word);
        }
        if (!isset($db_query)) {
          $db_query = $query;
        }
        elseif ($not_nested) {
          $db_query->union($query, 'UNION');
        }
        else {
          $db_query->union($query, 'UNION ALL');
        }
      }
    }

    if ($nested) {
      $word = '';
      foreach ($nested as $k) {
        $query = $this->createKeysQuery($k, $fields, $all_fields);
        if (!$neg) {
          $word .= ' ';
          $var = ':word' . strlen($word);
          $query->addExpression($var, 'word', array($var => $word));
        }
        if (!isset($db_query)) {
          $db_query = $query;
        }
        elseif ($not_nested) {
          $db_query->union($query, 'UNION');
        }
        else {
          $db_query->union($query, 'UNION ALL');
        }
      }
    }

    if (isset($db_query) && !$not_nested) {
      $db_query = db_select($db_query, 't', $this->query_options);
      $db_query->addField('t', 'item_id', 'item_id');
      if (!$neg) {
        $db_query->addExpression('SUM(t.score)', 'score');
        $db_query->groupBy('t.item_id');
        $grouped = 1;
      }
      if ($conj == 'AND' && $subs > 1) {
        $var = ':subs' . ((int) $subs);
        // @todo: This has to wait for the patch in http://drupal.org/node/423888
//        if (empty($db_query->getGroupBy())) {
        if (!isset($grouped)) {
          $db_query->groupBy('t.item_id');
          $grouped = 1;
        }
//        }
        if ($mul_words) {
          $db_query->having('COUNT(DISTINCT t.word) >= ' . $var, array($var => $subs));
        }
        else {
          $db_query->having('COUNT(DISTINCT t.word) >= ' . $var, array($var => $subs));
        }
      }
    }

    if ($negated) {
      if (!isset($db_query) || $conj == 'OR') {
        if (isset($all_fields['search-api-language'])) {
          // We use this table because all items should be contained exactly once.
          $table = $all_fields['search-api-language']['table'];
        }
        else {
          $distinct = TRUE;
          foreach ($all_fields as $field) {
            $table = $field['table'];
            if (!search_api_is_list_type($field['type']) && !search_api_is_text_type($field['type'])) {
              unset($distinct);
              break;
            }
          }
        }
        if (isset($db_query)) {
          // We are in a rather bizarre case where the keys are something like "a OR (NOT b)".
          $old_query = $db_query;
        }
        $db_query = db_select($table, 't', $this->query_options);
        $db_query->addField('t', 'item_id', 'item_id');
        if (!$neg) {
          $db_query->addExpression(':score', 'score', array(':score' => 1));
        }
        if (isset($distinct)) {
          $db_query->distinct();
        }
      }

      if ($conj == 'AND') {
        foreach ($negated as $k) {
          $db_query->condition('t.item_id', $this->createKeysQuery($k, $fields, $all_fields), 'NOT IN');
        }
      }
      else {
        $or = db_or();
        foreach ($negated as $k) {
          $or->condition('t.item_id', $this->createKeysQuery($k, $fields, $all_fields), 'NOT IN');
        }
        if (isset($old_query)) {
          $or->condition('t.item_id', $old_query, 'NOT IN');
        }
        $db_query->condition($or);
      }
    }

    return $db_query;
  }

  /**
   * Helper method for finding any needed table for a filter query.
   */
  protected function findTable(array $filters, array $fields) {
    foreach ($filters as $filter) {
      if (is_array($filter)) {
        return $fields[$filter[0]]['table'];
      }
    }
    foreach ($filters as $filter) {
      if (is_object($filter)) {
        $ret = $this->findTable($filter->getFilters(), $fields);
        if ($ret) {
          return $ret;
        }
      }
    }
  }

  /**
   * Helper method for creating a condition for filtering search results.
   *
   * @return QueryConditionInterface
   */
  protected function createFilterCondition(SearchApiQueryFilterInterface $filter, array $fields, SelectQueryInterface $db_query) {
    $cond = db_condition($filter->getConjunction());
    $empty = TRUE;
    foreach ($filter->getFilters() as $f) {
      if (is_object($f)) {
        $c = $this->createFilterCondition($f, $fields, $db_query);
        if ($c) {
          $empty = FALSE;
          $cond->condition($c);
        }
      }
      else {
        $empty = FALSE;
        if (!isset($fields[$f[0]])) {
          throw new SearchApiException(t('Unknown field in filter clause: !field.', array('!field' => $f[0])));
        }
        if (search_api_is_text_type($fields[$f[0]]['type'])) {
          $keys = array('#conjunction' => 'AND', '#negation' => TRUE, $f[1]);
          $keys = $this->prepareKeys($keys);
          $query = $this->createKeysQuery($keys, array($fields[$f[0]]), $fields);
          $cond->condition('t.item_id', $query, $f[2] == '<>' || $f[2] == '!=' ? 'NOT IN' : 'IN');
        }
        else {
          $alias = $this->getTableAlias($fields[$f[0]], $db_query);
          $cond->condition($alias . '.value', $f[1], $f[2]);
        }
      }
    }
    return $empty ? NULL : $cond;
  }

  /**
   * Helper method for adding a field's table to a database query.
   */
  protected function getTableAlias(array $field, SelectQueryInterface $db_query) {
    foreach ($db_query->getTables() as $alias => $info) {
      $table = $info['table'];
      if (is_scalar($table) && $table == $field['table']) {
        return $alias;
      }
    }
    return $db_query->join($field['table'], 't', 't.item_id = %alias.item_id');
  }

  /**
   * Helper method for setting the database to the one selected by the user.
   */
  protected function setDb() {
    if (!isset($this->previous_db)) {
      list($key, $target) = explode(':', $this->options['database'], 2);
      $this->previous_db = db_set_active($key);
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
    if (isset($this->previous_db)) {
      db_set_active($this->previous_db);
      $this->previous_db = NULL;
      return TRUE;
    }
    return FALSE;
  }

}
