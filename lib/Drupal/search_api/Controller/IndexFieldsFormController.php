<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\IndexFieldsFormController.
 */

namespace Drupal\search_api\Controller;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Entity\EntityFormController;

/**
 * Provides a fields form controller for the Index entity.
 */
class IndexFieldsFormController extends EntityFormController {

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

  public function form(array $form, array &$form_state) {
    dpm(\Drupal::service('search_api.datasource.plugin.manager')->getDefinitions());
    
    return parent::form($form, $form_state);
  }

  // @todo: Needs implementation.

}
