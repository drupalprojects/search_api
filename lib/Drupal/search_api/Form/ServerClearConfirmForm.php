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
    // Initialize the ignored indexes as an empty array. This variable will
    // contain all the index labels that failed to reindex.
    $ignored_indexes = array();
    // Iterate through the attached indexes.
    foreach ($entity->getIndexes() as $index) {
      // Check if reindexing items is possible.
      if ($index->reindex()) {
        // Delete all indexed data for the current index.
        $entity->deleteAllItems($index);
      }
      else {
        // Add the index label to the list of ignored or failed indexes.
        $ignored_indexes[] = l($index->label(), $index->getSystemPath('canonical'));
      }
    }
    // Check if any index was skipped.
    if ($ignored_indexes) {
      // Build the ignored indexes message argument.
      $message_args = array(
        '%name' => $entity->label(),
        '!indexes' => implode(', ', $ignored_indexes),
      );
      // Build the ignored indexes message.
      $message = $this->translationManager()->formatPlural(count($ignored_indexes), 'The indexed data from search index !indexes was ignored during indexed data clear for search server %name.', 'The indexed data from search indexes !indexes were ignored during indexed data clear for search server %name.', $message_args);
      // Notify user about some indexes that were ignored from clear operation.
      drupal_set_message($message, 'warning');
    }
    else {
      // Notify user about all indexed data was cleared.
      drupal_set_message($this->t('The indexed data from the search server %name was successfully cleared.', array('%name' => $entity->label())));
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
