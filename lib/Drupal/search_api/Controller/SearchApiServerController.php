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
use Drupal\search_api\Server\ServerInterface;

class SearchApiServerController extends ControllerBase {

  /**
   * Displays information about a Search API server.
   * 
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   An instance of ServerInterface.
   * 
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(ServerInterface $search_api_server) {
    // Build the Search API server information.
    $render = array(
      'view' => array(
        '#theme' => 'search_api_server',
        '#server' => $search_api_server,
      ),
      '#attached' => array(
        'css' => array(
          drupal_get_path('module', 'search_api') . '/css/search_api.admin.css'
        ),
      ),
    );
    // Check if the server is enabled.
    if ($search_api_server->status()) {
      // Attach the server status form.
      $render['form'] = $this->formBuilder()->getForm('Drupal\search_api\Form\ServerStatusForm', $search_api_server);
    }
    return $render;
  }

  /**
   * The _title_callback for the search_api.server_view route.
   *
   * @param \Drupal\search_api\Server\ServerInterface $search_api_server
   *   An instance of ServerInterface.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(ServerInterface $search_api_server) {
    return String::checkPlain($search_api_server->label());
  }

  public function serverBypassEnable(ServerInterface $search_api_server) {
    $search_api_server->setStatus(TRUE)->save();
    $route = $search_api_server->urlInfo();

    return $this->redirect($route['route_name'], $route['route_parameters']);
  }

}