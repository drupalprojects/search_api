<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersFormController.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityFormController;

/**
 * Provides a filters form for the Index entity.
 */
class IndexFiltersForm extends EntityFormController {

  /**
   * The book being displayed.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $entity;


  /**
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    $form['#title'] = $this->entity->label();

    $form['something'] = array(
      '#markup' => 'test'
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);

    // Remove the delete action
    unset($actions['delete']);

    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * @see book_remove_button_submit()
   */
  public function submit(array $form, array &$form_state) {
    // TODO
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    //$form_state['redirect_route'] = $this->entity->urlInfo('book-remove-form');
  }
}
