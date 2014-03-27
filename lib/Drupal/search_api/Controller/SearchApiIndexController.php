<?php

/**
* @file
* Contains \Drupal\search_api\Controller\SearchApiIndexController.
*
* Small controller to handle Index enabling without confirmation task
*/

namespace Drupal\search_api\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Index\IndexInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchApiIndexController extends ControllerBase {

  /**
   * Displays information about a Search API index.
   * 
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   * 
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(IndexInterface $search_api_index) {
    // Build the Search API index information.
    $render = array(
      'view' => array(
        '#theme' => 'search_api_index',
        '#index' => $search_api_index,
      ),
    );
    // Check if the index is enabled and can be written to.
    if ($search_api_index->status() && !$search_api_index->isReadOnly()) {
      // Attach the index status form.
      // @todo: Specify the form class for deleting or reindexing data on an
      // index.
      //$render['form'] = $this->formBuilder()->getForm('?', $search_api_index);
    }
    return $render;
  }

  /**
   * The _title_callback for the search_api.index_view route.
   *
   * @param \Drupal\search_api\Index\IndexInterface $search_api_index
   *   An instance of IndexInterface.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(IndexInterface $search_api_index) {
    return String::checkPlain($search_api_index->label());
  }

  public function indexBypassEnable(Request $request, IndexInterface $search_api_index) {
    if (($token = $request->get('token')) && \Drupal::csrfToken()->validate($token, $search_api_index->id())) {
      // Toggle the entity status.
      $search_api_index->setStatus(TRUE)->save();

      if ($search_api_index->status()) {
        if (!$search_api_index->getServer()->status()) {
          $search_api_index->setStatus(FALSE);
        }
      }

      $route = $search_api_index->urlInfo();
      return $this->redirect($route['route_name'], $route['route_parameters']);
    }
  }

}