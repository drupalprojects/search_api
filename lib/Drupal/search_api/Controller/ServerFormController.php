<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\ServerFormController.
 */

namespace Drupal\search_api\Controller;

/*
 * Include required classes and interfaces.
 */
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Service\ServicePluginManager;
use Drupal\search_api\Server\ServerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a form controller for the Server entity.
 */
class ServerFormController extends EntityFormController {

  /**
   * The server storage controller.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $storageController;

  /**
   * The service plugin manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\search_api\Service\ServicePluginManager
   */
  protected $servicePluginManager;

  /**
   * Create a ServerFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Service\ServicePluginManager $service_plugin_manager
   *   The service plugin manager.
   */
  public function __construct(EntityManager $entity_manager, ServicePluginManager $service_plugin_manager) {
    // Setup object members.
    $this->storageController = $entity_manager->getStorageController('search_api_server');
    $this->servicePluginManager = $service_plugin_manager;
  }

  /**
   * Get the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageControllerInterface
   *   An instance of EntityStorageControllerInterface.
   */
  protected function getStorageController() {
    return $this->storageController;
  }

  /**
   * Get the service plugin manager.
   *
   * @return \Drupal\search_api\Service\ServicePluginManager
   *   An instance of ServicePluginManager.
   */
  protected function getServicePluginManager() {
    return $this->servicePluginManager;
  }

  /**
   * Get a list of service plugin definitions for use with a select element.
   *
   * @return array
   *   An associative array of service plugin names, keyed by the service plugin
   *   ID.
   */
  protected function getServicePluginDefinitionOptions() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the service plugin definitions.
    foreach ($this->getServicePluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
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
      $container->get('search_api.service.plugin.manager')
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
    // Build the service configuration form.
    $this->buildServiceConfigForm($form, $form_state, $entity);
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
        'exists' => array($this->getStorageController(), 'load'),
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
    // Build the service plugin selection element.
    $form['servicePluginId'] = array(
      '#type' => 'select',
      '#title' => $this->t('Service'),
      '#description' => $this->t('Choose a service to use for this server.'),
      '#options' => $this->getServicePluginDefinitionOptions(),
      '#default_value' => $server->hasValidService() ? $server->getService()->getPluginId() : NULL,
      '#required' => TRUE,
      '#ajax' => array(
        'callback' => array($this, 'buildAjaxServiceConfigForm'),
        'wrapper' => 'search-api-service-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
  }

  /**
   * Build the service configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   An instance of ServerInterface.
   */
  public function buildServiceConfigForm(array &$form, array &$form_state, ServerInterface $server) {
    // Build the service plugin configuration container element.
    $form['servicePluginConfig'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-service-config-form',
      ),
      '#tree' => TRUE,
    );
    // Check if the server has a valid service configured.
    if ($server->hasValidService()) {
      // Get the service.
      $service = $server->getService();
      // Get the service plugin definition.
      $service_plugin_definition = $service->getPluginDefinition();
      // Build the service configuration form.
      if (($service_plugin_config_form = $service->buildConfigurationForm(array(), $form_state))) {
        // Modify the service plugin configuration container element.
        $form['servicePluginConfig']['#type'] = 'details';
        $form['servicePluginConfig']['#title'] = $this->t('Configure @plugin', array('@plugin' => $service_plugin_definition['label']));
        $form['servicePluginConfig']['#description'] = String::checkPlain($service_plugin_definition['description']);
        $form['servicePluginConfig']['#collapsed'] = !$server->isNew();
        // Attach the build service plugin configuration form.
        $form['servicePluginConfig'] += $service_plugin_config_form;
      }
    }
    // Do not notify the user about a missing service plugin if a new server
    // is being configured.
    elseif (!$server->isNew()) {
      // Notify the user about the missing service plugin.
      drupal_set_message($this->t('The service plugin is missing or invalid.'), 'error');
    }
  }

  /**
   * Build the service plugin configuration form in context of an Ajax request.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the structure of the form.
   */
  public function buildAjaxServiceConfigForm(array $form, array &$form_state) {
    // Get the service plugin configuration form.
    return $form['servicePluginConfig'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // Perform default entity form validate.
    parent::validate($form, $form_state);
    // Get the entity.
    $entity = $this->getEntity();
    // Get the current service plugin ID.
    $service_plugin_id = $entity->hasValidService() ? $entity->getService()->getPluginId() : NULL;
    // Check if the service plugin changed.
    if ($service_plugin_id !== $form_state['values']['servicePluginId']) {
      // Check if the service plugin configuration form input values exist.
      if (!empty($form_state['input']['servicePluginConfig'])) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        $form_state['input']['servicePluginConfig'] = array();
      }
      // Check if the service plugin configuration form values exist.
      if (!empty($form_state['values']['servicePluginConfig'])) {
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        $form_state['values']['servicePluginConfig'] = array();
      }
    }
    // Check if the entity has a valid service plugin.
    elseif ($entity->hasValidService()) {
      // Validate the service plugin configuration form.
      $entity->getService()->validateConfigurationForm($form['servicePluginConfig'], $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Get the entity.
    $entity = $this->getEntity();
    // Get the current service plugin ID.
    $service_plugin_id = $entity->hasValidService() ? $entity->getService()->getPluginId() : NULL;
    // Perform default entity form submittion.
    $entity = parent::submit($form, $form_state);
    // Check if the service plugin changed.
    if ($service_plugin_id !== $form_state['values']['servicePluginId']) {
      // Notify the user about the service configuration change.
      drupal_set_message($this->t('Please configure the used service.'), 'warning');
      // Rebuild the form.
      $form_state['rebuild'] = TRUE;
    }
    // Check if the entity has a valid service plugin.
    elseif ($entity->hasValidService()) {
      // Get the service from the entity.
      $service = $entity->getService();
      // Submit the service plugin configuration form.
      $service->submitConfigurationForm($form['servicePluginConfig'], $form_state);
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
        $this->getEntity()->save();
        // Notify the user that the server was created.
        drupal_set_message($this->t('The server was successfully saved.'));
        // Redirect to the server page.
        $form_state['redirect'] = $this->url('search_api.server_overview');
      }
      catch (Exception $ex) {
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
    // Build the route parameters.
    $params = array('search_api_server' => $entity->id());
    // Redirect to the entity delete confirm page.
    $form_state['redirect'] = $this->url('search_api.server_delete', $params);
  }

}
