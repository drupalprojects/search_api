<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\ServerForm.
 */

namespace Drupal\search_api\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Backend\BackendPluginManager;
use Drupal\search_api\Server\ServerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a form for the Server entity.
 */
class ServerForm extends EntityForm {

  /**
   * The server storage controller.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The backend plugin manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\search_api\Backend\BackendPluginManager
   */
  protected $backendPluginManager;

  /**
   * Create a ServerFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Backend\BackendPluginManager $backend_plugin_manager
   *   The backend plugin manager.
   */
  public function __construct(EntityManager $entity_manager, BackendPluginManager $backend_plugin_manager) {
    // Setup object members.
    $this->storage = $entity_manager->getStorage('search_api_server');
    $this->backendPluginManager = $backend_plugin_manager;
  }

  /**
   * Get the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   */
  protected function getStorage() {
    return $this->storage;
  }

  /**
   * Get the backend plugin manager.
   *
   * @return \Drupal\search_api\Backend\BackendPluginManager
   *   An instance of BackendPluginManager.
   */
  protected function getBackendPluginManager() {
    return $this->backendPluginManager;
  }

  /**
   * Get a list of backend plugin definitions for use with a select element.
   *
   * @return array
   *   An associative array of backend plugin names, keyed by the backend plugin
   *   ID.
   */
  protected function getBackendPluginDefinitionOptions() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the backend plugin definitions.
    foreach ($this->getBackendPluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      // Add the plugin to the list.
      $options[$plugin_id] = String::checkPlain($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('plugin.manager.search_api.backend')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    // Check if the form is being rebuild.
    if (!empty($form_state['rebuild'])) {
      // Rebuild the entity with the form state values.
      $this->entity = $this->buildEntity($form, $form_state);
    }
    // Build the default entity form.
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $entity = $form_state['entity'] = $this->getEntity();
    // Check if the entity is being created.
    if ($entity->isNew()) {
      // Change the page title to 'Add server'.
      $form['#title'] = $this->t('Add search server');
    }
    else {
      // Change the page title to 'Edit @label'.
      $form['#title'] = $this->t('Edit search server @label', array('@label' => $entity->label()));
    }
    // Build the entity form.
    $this->buildEntityForm($form, $form_state, $entity);
    // Build the backend configuration form.
    $this->buildBackendConfigForm($form, $form_state, $entity);
    // Return the build form.
    return $form;
  }

  /**
   * Build the entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   An instance of ServerInterface.
   */
  public function buildEntityForm(array &$form, array &$form_state, ServerInterface $server) {
    // Build the name element.
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Server name'),
      '#description' => $this->t('Enter the displayed name for the server.'),
      '#default_value' => $server->label(),
      '#required' => TRUE,
    );
    // Build the machine name element.
    $form['machine_name'] = array(
      '#type' => 'machine_name',
      '#default_value' => $server->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => array(
        'exists' => array($this->getStorage(), 'load'),
        'source' => array('name'),
      ),
    );
    // Build the status element.
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Select if the server will be enabled.'),
      '#default_value' => $server->status(),
    );
    // Build the description element.
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the server.'),
      '#default_value' => $server->getDescription(),
    );
    // Build the backend plugin selection element.
    $form['backendPluginId'] = array(
      '#type' => 'select',
      '#title' => $this->t('Backend'),
      '#description' => $this->t('Choose a backend to use for this server.'),
      '#options' => $this->getBackendPluginDefinitionOptions(),
      '#default_value' => $server->hasValidBackend() ? $server->getBackend()->getPluginId() : NULL,
      '#required' => TRUE,
      '#ajax' => array(
        'callback' => array($this, 'buildAjaxBackendConfigForm'),
        'wrapper' => 'search-api-backend-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );

    // Attach the admin css.
    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';
  }

  /**
   * Build the backend configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   An instance of ServerInterface.
   */
  public function buildBackendConfigForm(array &$form, array &$form_state, ServerInterface $server) {
    // Build the backend plugin configuration container element.
    $form['backendPluginConfig'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-backend-config-form',
      ),
      '#tree' => TRUE,
    );
    // Check if the server has a valid backend configured.
    if ($server->hasValidBackend()) {
      // Get the backend.
      $backend = $server->getBackend();
      // Get the backend plugin definition.
      $backend_plugin_definition = $backend->getPluginDefinition();
      // Build the backend configuration form.
      if (($backend_plugin_config_form = $backend->buildConfigurationForm(array(), $form_state))) {
        // Check if the backend plugin changed.
        if (!empty($form_state['values']['backendPluginId'])) {
          // Notify the user about the backend configuration change.
          drupal_set_message($this->t('Please configure the used backend.'), 'warning');
        }

        // Modify the backend plugin configuration container element.
        $form['backendPluginConfig']['#type'] = 'details';
        $form['backendPluginConfig']['#title'] = $this->t('Configure @plugin', array('@plugin' => $backend_plugin_definition['label']));
        $form['backendPluginConfig']['#description'] = String::checkPlain($backend_plugin_definition['description']);
        $form['backendPluginConfig']['#open'] = TRUE;
        // Attach the build backend plugin configuration form.
        $form['backendPluginConfig'] += $backend_plugin_config_form;
      }
    }
    // Do not notify the user about a missing backend plugin if a new server
    // is being configured.
    elseif (!$server->isNew()) {
      // Notify the user about the missing backend plugin.
      drupal_set_message($this->t('The backend plugin is missing or invalid.'), 'error');
    }
  }

  /**
   * Build the backend plugin configuration form in context of an Ajax request.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the structure of the form.
   */
  public function buildAjaxBackendConfigForm(array $form, array &$form_state) {
    // Get the backend plugin configuration form.
    return $form['backendPluginConfig'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // Perform default entity form validate.
    parent::validate($form, $form_state);
    // Get the entity.
    $entity = $this->getEntity();
    // Get the current backend plugin ID.
    $backend_plugin_id = $entity->hasValidBackend() ? $entity->getBackend()->getPluginId() : NULL;
    // Check if the backend plugin changed.
    if ($backend_plugin_id !== $form_state['values']['backendPluginId']) {
      // Check if the backend plugin configuration form input values exist.
      if (!empty($form_state['input']['backendPluginConfig'])) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        $form_state['input']['backendPluginConfig'] = array();
      }
      // Check if the backend plugin configuration form values exist.
      if (!empty($form_state['values']['backendPluginConfig'])) {
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        $form_state['values']['backendPluginConfig'] = array();
      }
    }
    // Check if the entity has a valid backend plugin.
    elseif ($entity->hasValidBackend()) {
      // Validate the backend plugin configuration form.
      if (isset($form_state['values']['backendPluginConfig'])) {
        $backend_form_state['values'] = $form_state['values']['backendPluginConfig'];
        $entity->getBackend()->validateConfigurationForm($form['backendPluginConfig'], $backend_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Perform default entity form submittion.
    $entity = parent::submit($form, $form_state);
    // Check if the entity has a valid backend plugin.
    if ($entity->hasValidBackend()) {
      // Submit the backend plugin configuration form.
      if (isset($form_state['values']['backendPluginConfig'])) {
        $backend_form_state['values'] = $form_state['values']['backendPluginConfig'];
        $entity->getBackend()->submitConfigurationForm($form['backendPluginConfig'], $backend_form_state);
      }
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    // Check if the form does not need to be rebuild.
    if (empty($form_state['rebuild'])) {
      // Catch any exception that may get thrown during save operation.
      try {
        // Save changes made to the entity.
        $entity = $this->getEntity();
        $entity->save();
        // Notify the user that the server was created.
        drupal_set_message($this->t('The server was successfully saved.'));
        // Redirect to the server page.
        $form_state['redirect_route'] = array(
          'route_name' => 'search_api.server_view',
          'route_parameters' => array(
            'search_api_server' => $entity->id(),
          ),
        );
      }
      catch (\Exception $ex) {
        // Rebuild the form.
        $form_state['rebuild'] = TRUE;
        // Log the exception to the watchdog.
        watchdog_exception('Search API', $ex);
        // Notify the user that the save operation failed.
        drupal_set_message($this->t('The server could not be saved.'), 'error');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    // Get the entity.
    $entity = $this->getEntity();
    // Redirect to the entity delete confirm page.
    $form_state['redirect_route'] = array(
      'route_name' => 'search_api.server_delete',
      'route_parameters' => array(
        'search_api_server' => $entity->id(),
      ),
    );
  }

}
