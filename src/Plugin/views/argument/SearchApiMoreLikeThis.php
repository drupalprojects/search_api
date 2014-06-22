<?php

/**
 * @file
 * Contains SearchApiViewsHandlerArgumentMoreLikeThis.
 */

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\search_api\Exception\SearchApiException;

/**
 * Views argument handler providing a list of related items for search servers
 * supporting the "search_api_mlt" feature.
 *
 * @ViewsArgument("search_api_more_like_this")
 */
class SearchApiMoreLikeThis extends SearchApiArgument {

  /**
   * Specify the options this filter uses.
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    unset($options['break_phrase']);
    unset($options['not']);
    $options['fields'] = array('default' => array());
    return $options;
  }

  /**
   * Extend the options form a bit.
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);
    unset($form['break_phrase']);
    unset($form['not']);

    $index = entity_load('search_api_index', substr($this->table, 17));
    if (!empty($index->options['fields'])) {
      $fields = array();
      foreach ($index->getFields() as $key => $field) {
        $fields[$key] = $field['name'];
      }
    }
    if (!empty($fields)) {
      $form['fields'] = array(
        '#type' => 'select',
        '#title' => $this->t('Fields for Similarity'),
        '#description' => $this->t('Select the fields that will be used for finding similar content. If no fields are selected, all available fields will be used.'),
        '#options' => $fields,
        '#size' => min(8, count($fields)),
        '#multiple' => TRUE,
        '#default_value' => $this->options['fields'],
      );
    }
    else {
      $form['fields'] = array(
        '#type' => 'value',
        '#value' => array(),
      );
    }
  }

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    try {
      $server = $this->query->getIndex()->getServer();
      if (!$server->supportsFeature('search_api_mlt')) {
        $class = $server->getService()->getPluginDefinition()['class'];
        watchdog('search_api_views', 'The search service "@class" does not offer "More like this" functionality.',
          array('@class' => $class), WATCHDOG_ERROR);
        $this->query->abort();
        return;
      }
      $fields = $this->options['fields'] ? $this->options['fields'] : array();
      if (empty($fields)) {
        foreach ($this->query->getIndex()->options['fields'] as $key => $field) {
          $fields[] = $key;
        }
      }
      $mlt = array(
        'id' => $this->argument,
        'fields' => $fields,
      );
      $this->query->getSearchApiQuery()->setOption('search_api_mlt', $mlt);
    }
    catch (SearchApiException $e) {
      $this->query->abort($e->getMessage());
    }
  }

}
