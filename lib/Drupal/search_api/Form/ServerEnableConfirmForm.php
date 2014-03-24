<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\ServerEnableConfirmForm.
 */

namespace Drupal\search_api\Form;

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
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array(
      'route_name' => 'search_api.server_view',
      'route_parameters' => array(
        'search_api_server' => $this->entity->id(),
      ),
    );
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
    // Redirect to the server view page.
    $form_state['redirect_route'] = $this->getCancelRoute();
  }

}
