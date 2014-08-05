<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexDeleteConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a delete confirm form for the Index entity.
 */
class IndexDeleteConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the search index %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('search_api.index_view', array('search_api_index' => $this->entity->id()));
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
  public function submit(array $form, FormStateInterface $form_state) {
    // Delete the entity.
    $this->entity->delete();
    // Notify the user about the index removal.
    drupal_set_message($this->t('The search index %name has been removed.', array('%name' => $this->entity->label())));
    // Redirect to the overview page.
    $form_state->setRedirect(new Url('search_api.overview'));
  }

}
