<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\ServerClearConfirmForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Defines a clear confirm form for the Server entity.
 */
class ServerClearConfirmForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear all indexed data from the search server %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will permanently remove all data currently indexed on this server. Before the data is reindexed, searches on the indexes associated with this server will not return any results. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array(
      'route_name' => 'search_api.server_view',
      'route_parameters' => array(
        'search_api_server' => $this->entity->id(),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Get the search server entity object.
    $entity = $this->getEntity();
    // Initialize the success flag which will indicate whether all indexes
    // successfully schedule a reindex.
    $success = TRUE;
    // Iterate through the attached indexes.
    foreach ($entity->getIndexes() as $index) {
      // @todo: Should we ignore disabled or readonly indexes? Because this is
      // preventing the server from clearing indexed data.
      // Reindex the search index.
      $success &= $index->reindex();
    }
    // Check if all attached indexes successfully scheduled a reindex of their
    // data.
    if ($success) {
      // Delete all indexed data from the search server.
      $entity->deleteAllItems();
      // Notify the user about the indeded data being cleared.
      drupal_set_message($this->t('The indexed data from the search server %name was successfully cleared.', array('%name' => $entity->label())));
    }
    else {
      // Notify the user about the failure to clear the indexed data.
      drupal_set_message($this->t('Failed to clear all indexed data from the search server %name.', array('%name' => $entity->label())), 'error');
    }
    // Redirect to the server view page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.server_view',
      'route_parameters' => array(
        'search_api_server' => $entity->id(),
      ),
    );
  }

}
