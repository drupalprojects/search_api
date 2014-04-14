<?php

/**
 * @file
 * Contains \Drupal\search_api\Controller\SearchApiIndexController.
 */

namespace Drupal\search_api\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Index\IndexInterface;

/**
 * Provides route responses for Search API indexes.
 */
class SearchApiIndexController extends ControllerBase {

  /**
   * Displays information about a Search API index.
   * 
   * @param \Drupal\search_api\Index\IndexInterface $search_api_index
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
      $render['form'] = $this->formBuilder()->getForm('Drupal\search_api\Form\IndexStatusForm', $search_api_index);
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

  /**
   * Enables a Search API index without a confirmation form.
   *
   * @param \Drupal\search_api\Index\IndexInterface $search_api_index
   *   An instance of IndexInterface.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function indexBypassEnable(IndexInterface $search_api_index) {
    // Enable the index.
    $search_api_index->setStatus(TRUE)->save();

    // \Drupal\search_api\Entity\Index::preSave() doesn't allow an index to be
    // enabled if its server is not set or disabled.
    if ($search_api_index->status()) {
      // Notify the user about the status change.
      drupal_set_message($this->t('The search index %name has been enabled.', array('%name' => $search_api_index->label())));
    }
    else {
      // Notify the user that the status change did not succeed.
      drupal_set_message($this->t('The search index %name could not be enabled. Check if its server is set and enabled.', array('%name' => $search_api_index->label())));
    }

    // Redirect to the index edit page.
    $url = $search_api_index->urlInfo();
    return $this->redirect($url->getRouteName(), $url->getRouteParameters());
  }

}
