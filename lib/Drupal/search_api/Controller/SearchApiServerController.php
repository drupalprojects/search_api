<?php

/**
* @file
* Contains \Drupal\search_api\Controller\SearchApiIndexController.
*
* Small controller to handle Index enabling without confirmation task
*/

namespace Drupal\search_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Server\ServerInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchApiServerController extends ControllerBase {

  public function serverBypassEnable(Request $request, ServerInterface $search_api_server) {
    if (($token = $request->get('token')) && \Drupal::csrfToken()->validate($token, $search_api_server->id())) {

      $search_api_server->setStatus(TRUE)->save();
      $route = $search_api_server->urlInfo();

      return $this->redirect($route['route_name'], $route['route_parameters']);
    }
  }

}