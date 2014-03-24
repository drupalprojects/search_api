<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexDisableConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a disable confirm form for the Index entity.
 */
class IndexDisableConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the search index %name?', array('%name' => $this->entity->label()));
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
    return array('route_name' => 'search_api.index_view');
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
  public function submit(array $form, array &$form_state) {
    // Toggle the entity status.
    $this->entity->setStatus(FALSE)->save();
    // Notify the user about the index removal.
    drupal_set_message($this->t('The search index %name has been disabled.', array('%name' => $this->entity->label())));
    // Get the cancel route.
    $cancel_route = $this->getCancelRoute();
    // Redirect to the index overview page.
    $form_state['redirect'] = $this->url($cancel_route['route_name']);
  }

}
