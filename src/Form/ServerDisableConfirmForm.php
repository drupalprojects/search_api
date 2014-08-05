<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\ServerDisableConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a disable confirm form for the Server entity.
 */
class ServerDisableConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the search server %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Disabling a server will also disable all attached indexes. It will also clear the tracking tables and if views is enabled it will disable the following views: insert list of views here');
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
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    // Toggle the entity status.
    $this->entity->setStatus(FALSE)->save();

    // Notify the user about the server removal.
    drupal_set_message($this->t('The search server %name has been disabled.', array('%name' => $this->entity->label())));
    // Redirect to the overview page.
    $form_state->setRedirect(new Url('search_api.overview'));
  }

}
