<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexClearConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a clear confirm form for the Index entity.
 */
class IndexClearConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear the indexed data for the search index %name?', array('%name' => $this->entity->label()));
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
  public function submit(array $form, array &$form_state) {
    // Get the search index entity object.
    $entity = $this->getEntity();
    // Clear the index.
    /** @var \Drupal\search_api\Index\IndexInterface $entity */
    $entity->clear();
    // Redirect to the index view page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.index_view',
      'route_parameters' => array(
        'search_api_index' => $entity->id(),
      ),
    );
  }

}
