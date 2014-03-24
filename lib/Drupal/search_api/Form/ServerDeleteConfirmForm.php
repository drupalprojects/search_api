<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\ServerDeleteConfirmForm.
 */

namespace Drupal\search_api\Form;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a delete confirm form for the Server entity.
 */
class ServerDeleteConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the search server %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array('route_name' => 'search_api.server_overview');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Delete the entity.
    $this->entity->delete();
    // Notify the user about the server removal.
    drupal_set_message(t('The search server %name has been removed.', array('%name' => $this->entity->label())));
    // Get the cancel route.
    $cancel_route = $this->getCancelRoute();
    // Redirect to the server overview page.
    $form_state['redirect'] = $this->url($cancel_route['route_name']);
  }

}
