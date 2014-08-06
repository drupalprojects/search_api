<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\ServerStatusForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\search_api\Server\ServerInterface;

/**
 * Form which allows basic operations on a server, e.g. clear indexed data.
 */
class ServerStatusForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_server_status';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ServerInterface $server = NULL) {
    // Attach the server to the form.
    $form['#server'] = $server;

    // Allow authorized users to clear the indexed data on this server.
    $form['actions']['#type'] = 'actions';
    $form['actions']['clear'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Delete all indexed data on this server'),
      '#button_type' => 'danger',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api\Server\ServerInterface $server */
    $server = $form['#server'];
    // Redirect to the server clear page.
    $form_state->setRedirect('search_api.server_clear', array('search_api_server' => $server->id()));
  }

}
