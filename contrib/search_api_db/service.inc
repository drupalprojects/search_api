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
      search_api_server_edit($this, array('options' => $this->options));
      return $this;
    }

    $prefix = 'search_api_db_' . $this->id . '_' . $index->id . '_';
    foreach ($index->options['fields'] as $name => $field) {
      if (empty($field['indexed'])) {
        continue;
      }
      $this->createFieldTable($field, $prefix . $name);
      $indexes[$index->id][$name]['table'] = $prefix . $name;
      $indexes[$index->id][$name]['type'] = $field['type'];
    }
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
    db_create_table($table);
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
        return array('type' => 'int');
      case 'decimal':
        return array('type' => 'float');
      case 'date':
        return array('type' => 'datetime');
      case 'boolean':
        return array('type' => 'int', 'size' => 'tiny');

      default:
        throw new SearchApiException(t('Unknown field type @type. Database search module might be out of sync with Search API.', array('@type' => $type)));
    }
  }

  public function fieldsUpdated(SearchApiIndex $index) {

  }

  public function removeIndex($index) {

  }

  public function indexItems(SearchApiIndex $index, array $items) {
debug_var($items);
  }

  public function deleteItems($ids, SearchApiIndex $index = NULL) {

  }

  public function query(SearchApiIndex $index, $options = array()) {

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
