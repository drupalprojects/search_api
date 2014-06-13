<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexDisableConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

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
    return new Url(
      'search_api.index_view',
      array(
        'search_api_index' => $this->entity->id(),
      )
    );
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

    // Notify the user about the status change.
    drupal_set_message($this->t('The search index %name has been disabled.', array('%name' => $this->entity->label())));

    // Redirect to the overview page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.overview',
    );
  }

}
