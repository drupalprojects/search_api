<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\IndexWorkflowFormController.
 */

namespace Drupal\search_api\Controller;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Entity\EntityFormController;

/**
 * Provides a workflow form controller for the Index entity.
 */
class IndexWorkflowFormController extends EntityFormController {

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, array &$form_state) {
    // Get the default entity actions.
    $actions = parent::actions($form, $form_state);
    // Remove the delete action.
    unset($actions['delete']);
    // Return the modified actions.
    return $actions;
  }

  // @todo: Needs implementation.

}
