<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\ServerEnableConfirmForm.
 */

namespace Drupal\search_api\Form;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a enable confirm form for the Server entity.
 */
class ServerEnableConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to enable the search server %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return ''; // @fixme: What description message should be shown here?
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
    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Toggle the entity status.
    $this->entity->setStatus(TRUE)->save();
    // Notify the user about the server removal.
    drupal_set_message($this->t('The search server %name has been enabled.', array('%name' => $this->entity->label())));
    // Get the cancel route.
    $cancel_route = $this->getCancelRoute();
    // Redirect to the server overview page.
    $form_state['redirect'] = $this->url($cancel_route['route_name']);
  }

}
