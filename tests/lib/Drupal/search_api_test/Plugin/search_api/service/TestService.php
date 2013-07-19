<?php

/**
 * @file
 * Contains Drupal\search_api_test\Plugin\search_api\service\TestService.
 */

namespace Drupal\search_api_test\Plugin\search_api\service;

use Drupal\search_api\Plugin\search_api\service\ServicePluginBase;

/**
 * Test implementation of a Search API service class.
 */
class TestService extends ServicePluginBase {

  public function configurationForm(array $form, array &$form_state) {
    $form = array(
      'test' => array(
        '#type' => 'textfield',
        '#title' => 'Test option',
      ),
    );

    if (!empty($this->options)) {
      $form['test']['#default_value'] = $this->options['test'];
    }

    return $form;
  }

  public function indexItems(SearchApiIndex $index, array $items) {
    // Refuse to index items with IDs that are multiples of 8 unless the
    // "search_api_test_index_all" state is set.
    if (Drupal::state()->get('search_api_test_index_all')) {
      return $this->index($index, array_keys($items));
    }
    $ret = array();
    foreach ($items as $id => $item) {
      if ($id % 8) {
        $ret[] = $id;
      }
    }
    return $this->index($index, $ret);
  }

  protected function index(SearchApiIndex $index, array $ids) {
    $this->options += array('indexes' => array());
    $this->options['indexes'] += array($index->machine_name => array());
    $this->options['indexes'][$index->machine_name] += drupal_map_assoc($ids);
    sort($this->options['indexes'][$index->machine_name]);
    $this->server->save();
    return $ids;
  }

  /**
   * Override so deleteItems() isn't called which would otherwise lead to the
   * server being updated and, eventually, to a notice because there is no
   * server to be updated anymore.
   */
  public function preDelete() {}

  public function deleteItems($ids = 'all', SearchApiIndex $index = NULL) {
    if ($ids == 'all') {
      if ($index) {
        $this->options['indexes'][$index->machine_name] = array();
      }
      else {
        $this->options['indexes'] = array();
      }
    }
    else {
      foreach ($ids as $id) {
        unset($this->options['indexes'][$index->machine_name][$id]);
      }
    }
    $this->server->save();
  }

  public function search(SearchApiQueryInterface $query) {
    $options = $query->getOptions();
    $ret = array();
    $index_id = $query->getIndex()->machine_name;
    if (empty($this->options['indexes'][$index_id])) {
      return array(
        'result count' => 0,
        'results' => array(),
      );
    }
    $items = $this->options['indexes'][$index_id];
    $min = isset($options['offset']) ? $options['offset'] : 0;
    $max = $min + (isset($options['limit']) ? $options['limit'] : count($items));
    $i = 0;
    $ret['result count'] = count($items);
    $ret['results'] = array();
    foreach ($items as $id) {
      ++$i;
      if ($i > $max) {
        break;
      }
      if ($i > $min) {
        $ret['results'][$id] = array(
          'id' => $id,
          'score' => 1,
        );
      }
    }
    return $ret;
  }

  public function fieldsUpdated(SearchApiIndex $index) {
    return db_query('SELECT COUNT(*) FROM {search_api_test}')->fetchField() > 0;
  }

}

