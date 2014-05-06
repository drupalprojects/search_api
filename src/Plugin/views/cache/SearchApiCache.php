<?php

/**
 * @file
 * Contains the SearchApiViewsCache class.
 */

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\views\Plugin\views\cache\Time;

/**
 * Plugin class for caching Search API views.
 *
 * @todo Limit to Search API base tables.
 *
 * @ViewsCache(
 *   id = "search_api",
 *   title = @Translation("Search API specifc"),
 *   help = @Translation("Cache Search API views. (Other methods probably won't work with search views.)")
 * )
 */
class SearchApiCache extends Time {

  /**
   * Static cache for get_results_key().
   *
   * @var string
   */
  protected $_results_key = NULL;

  /**
   * Static cache for getSearchApiQuery().
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  protected $search_api_query = NULL;

  /**
   * {@inheritdoc}
   *
   * Also stores Search API's internal search results.
   */
  public function cacheSet($type) {
    if ($type != 'results') {
      parent::cacheSet($type);
      return;
    }

    $cid = $this->get_results_key();
    $data = array(
      'result' => $this->view->result,
      'total_rows' => isset($this->view->total_rows) ? $this->view->total_rows : 0,
      'current_page' => $this->view->get_current_page(),
      'search_api results' => $this->view->query->getSearchApiResults(),
    );
    \Drupal::cache($this->outputBin)->set($cid, $data, $this->cacheSetExpire($type));
  }

  /**
   * {@inheritdoc}
   *
   * Additionally stores successfully retrieved results with
   * search_api_current_search().
   */
  public function cacheGet($type) {
    if ($type != 'results') {
      return parent::cacheGet($type);
    }

    // Values to set: $view->result, $view->total_rows, $view->execute_time,
    // $view->current_page.
    if ($cache = \Drupal::cache($this->outputBin)->get($this->get_results_key())) {
      $cutoff = $this->cacheExpire($type);
      if (!$cutoff || $cache->created > $cutoff) {
        $this->view->result = $cache->data['result'];
        $this->view->total_rows = $cache->data['total_rows'];
        $this->view->setCurrentPage($cache->data['current_page']);
        $this->view->execute_time = 0;

        // Trick Search API into believing a search happened, to make facetting
        // et al. work.
        $query = $this->getSearchApiQuery();
        search_api_current_search($query->getOption('search id'), $query, $cache->data['search_api results']);

        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Use the Search API query as the main source for the key.
   */
  public function getResultsKey() {
    global $user;

    if (!isset($this->_results_key)) {
      $query = $this->getSearchApiQuery();
      $query->preExecute();
      $key_data = array(
        'query' => $query,
        'roles' => array_keys($user->getRoles()),
        'super-user' => $user->id() == 1, // special caching for super user.
        'language' => $GLOBALS['language']->language,
        'base_url' => $GLOBALS['base_url'],
      );
      // Not sure what gets passed in exposed_info, so better include it. All
      // other parameters used in the parent method are already reflected in the
      // Search API query object we use.
      if (isset($_GET['exposed_info'])) {
        $key_data['exposed_info'] = $_GET['exposed_info'];
      }

      $this->_results_key = $this->view->storage->id() . ':' . $this->view->getDisplay()->getPluginId() . ':results:' . md5(serialize($key_data));
    }

    return $this->_results_key;
  }

  /**
   * Get the Search API query object associated with the current view.
   *
   * @return \Drupal\search_api\Plugin\views\query\SearchApiQuery|null
   *   The Search API query object associated with the current view; or NULL if
   *   there is none.
   */
  protected function getSearchApiQuery() {
    if (!isset($this->search_api_query)) {
      $this->search_api_query = FALSE;
      if (isset($this->view->query) && $this->view->query instanceof SearchApiQuery) {
        $this->search_api_query = $this->view->query->getSearchApiQuery();
      }
    }

    return $this->search_api_query ? $this->search_api_query : NULL;
  }

}
