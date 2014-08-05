<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexClearConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\search_api\Exception\SearchApiException;

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
  public function getCancelUrl() {
    return new Url('search_api.index_view', array('search_api_index' => $this->entity->id()));
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api\Index\IndexInterface $entity */
    $entity = $this->getEntity();

    try {
      // Clear the index.
      $entity->clear();
    }
    catch (SearchApiException $e) {
      // Notify the user about the failure.
      drupal_set_message($this->t('Failed to clear the search index %name.', array('%name' => $entity->label())), 'error');
      watchdog_exception('search_api', $e, '%type while trying to clear the index %name: !message in %function (line %line of %file)', array('%name' => $entity->label()));
    }

    // Redirect to the index's "View" page.
    $form_state->setRedirect(new Url('search_api.index_view', array('search_api_index' => $entity->id())));
  }

}
