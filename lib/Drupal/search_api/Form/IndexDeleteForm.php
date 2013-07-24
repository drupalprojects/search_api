<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexDeleteForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Provides a confirmation form for deleting search indexes.
 */
class IndexDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the search index %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelPath() {
    return 'admin/config/search/search_api/index/' . $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $this->entity->delete();
    drupal_set_message(t('The search index %name has been removed.', array('%name' => $this->entity->label())));
    $form_state['redirect'] = 'admin/config/search/search_api';
  }

}
