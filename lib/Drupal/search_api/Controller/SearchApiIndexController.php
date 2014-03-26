<?php

/**
* @file
* Contains \Drupal\search_api\Controller\SearchApiIndexController.
*
* Small controller to handle Index enabling without confirmation task
*/

namespace Drupal\search_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Index\IndexInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchApiIndexController extends ControllerBase {

  public function indexBypassEnable(Request $request, IndexInterface $search_api_index) {
    if (($token = $request->get('token')) && \Drupal::csrfToken()->validate($token, $search_api_index->id())) {
      // Toggle the entity status.
      $search_api_index->setStatus(TRUE)->save();
      $route = $search_api_index->urlInfo();
      return $this->redirect($route['route_name'], $route['route_parameters']);
    }
  }

}