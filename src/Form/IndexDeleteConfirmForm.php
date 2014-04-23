<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexDeleteConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

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
  public function getCancelRoute() {
    return array(
      'route_name' => 'search_api.index_view',
      'route_parameters' => array(
        'search_api_index' => $this->entity->id(),
      ),
    );
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
    // Notify the user about the index removal.
    drupal_set_message(t('The search index %name has been removed.', array('%name' => $this->entity->label())));
    // Redirect to the overview page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.overview',
    );
  }

}
