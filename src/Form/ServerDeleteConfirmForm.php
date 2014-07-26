<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\ServerDeleteConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

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
  public function getDescription() {
    return $this->t('Deleting a server will disable all its indexes and their searches.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('search_api.server_view', array('search_api_server' => $this->entity->id()));
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
    drupal_set_message($this->t('The search server %name has been removed.', array('%name' => $this->entity->label())));
    // Redirect to the overview page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.overview',
    );
  }

}
