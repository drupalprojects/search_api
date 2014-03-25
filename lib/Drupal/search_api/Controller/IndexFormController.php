<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\IndexFormController.
 */

namespace Drupal\search_api\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\DatasourcePluginManager;
use Drupal\Component\Utility\String;

/**
 * Provides a form controller for the Index entity.
 */
class IndexFormController extends EntityFormController {

  /**
   * The entity manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The search datasource plugin manager.
   *
   * This object members must be set to anything other than private in order for
   * \Drupal\Core\DependencyInjection\DependencySerialization to detected.
   *
   * @var \Drupal\search_api\Datasource\DatasourcePluginManager
   */
  protected $datasourcePluginManager;

  /**
   * Create an IndexFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager
   *   The search datasource plugin manager.
   */
  public function __construct(EntityManager $entity_manager, DatasourcePluginManager $datasource_plugin_manager) {
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->datasourcePluginManager = $datasource_plugin_manager;
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * Get the search datasource plugin manager.
   *
   * @return \Drupal\search_api\Datasource\DatasourcePluginManager
   *   An instance of DatasourcePluginManager.
   */
  protected function getDatasourcePluginManager() {
    return $this->datasourcePluginManager;
  }

  /**
   * Get a list of datasource plugin definitions for use with a select element.
   *
   * @return array
   *   An associative array of datasource plugin names, keyed by the datasource
   *   plugin ID.
   */
  protected function getDatasourcePluginDefinitionOptions() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the datasource plugin definitions.
    foreach ($this->getDatasourcePluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      // Add the plugin to the list.
      $options[$plugin_id] = String::checkPlain($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * Get the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageControllerInterface
   *   An instance of EntityStorageControllerInterface.
   */
  protected function getIndexStorageController() {
    return $this->getEntityManager()->getStorageController('search_api_index');
  }

  /**
   * Get the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageControllerInterface
   *   An instance of EntityStorageControllerInterface.
   */
  protected function getServerStorageController() {
    return $this->getEntityManager()->getStorageController('search_api_server');
  }

  /**
   * Get a list of servers for use with a select element.
   *
   * @return array
   *   An associative array containing the server names, keyed by the server
   *   machine name.
   */
  protected function getServerOptions() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the servers.
    foreach ($this->getServerStorageController()->loadMultiple() as $server_machine_name => $server) {
      // Add the plugin to the list.
      $options[$server_machine_name] = String::checkPlain($server->label());
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('search_api.datasource.plugin.manager')
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
      // Change the page title to 'Add index'.
      $form['#title'] = $this->t('Add search index');
    }
    else {
      // Change the page title to 'Edit search index @label'.
      $form['#title'] = $this->t('Edit search index @label', array('@label' => $entity->label()));
    }
    // Build the entity form.
    $this->buildEntityForm($form, $form_state, $entity);
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
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function buildEntityForm(array &$form, array &$form_state, IndexInterface $index) {
    // Build the name element.
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $this->t('Enter the displayed name for the index.'),
      '#default_value' => $index->label(),
      '#required' => TRUE,
    );
    // Build the machine name element.
    $form['machine_name'] = array(
      '#type' => 'machine_name',
      '#default_value' => $index->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => array(
        'exists' => array($this->getIndexStorageController(), 'load'),
        'source' => array('name'),
      ),
    );
    // Build the description element.
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the index.'),
      '#default_value' => $index->getDescription(),
    );
    // Build the status element.
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Select if the index will be enabled. This will only take effect if the selected server is also enabled.'),
      '#default_value' => $index->status(),
      // Can't enable an index lying on a disabled server or no server at all.
      '#disabled' => !$index->status() && (!$index->hasValidServer() || !$index->getServer()->status()),
    );
    // Build the datasource element.
    $options = $this->getDatasourcePluginDefinitionOptions();

    $form['datasourcePluginId'] = array(
      '#type' => 'select',
      '#title' => $this->t('Datasource'),
      '#description' => $this->t('Select the datasource of items that will be indexed in this index. This setting cannot be changed afterwards.'),
      '#options' => $options,
      '#default_value' => $index->hasValidDatasource() ? $index->getDatasource()->getPluginId() : NULL,
      '#required' => TRUE,
      '#disabled' => !$index->isNew(),
      '#ajax' => array(
        'callback' => array($this, 'buildAjaxDatasourceConfigForm'),
        'wrapper' => 'search-api-datasource-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    // Build the datasource configuration form.
    $this->buildDatasourceConfigForm($form, $form_state, $index);
    // Build the server machine name element.
    $form['serverMachineName'] = array(
      '#type' => 'select',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select the server this index should reside on.'),
      '#options' => array('' => $this->t('< No server >')) + $this->getServerOptions(),
      '#default_value' => $index->hasValidServer() ? $index->getServer()->id() : NULL,
    );
    // Build the read only element.
    $form['readOnly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Read only'),
      '#description' => $this->t('Do not write to this index or track the status of items in this index.'),
      '#default_value' => $index->isReadOnly(),
    );
    // Build the options container element.
    $form['options'] = array(
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => t('Index options'),
      '#collapsed' => !$index->isNew(),
    );
    // Build the index directly element.
    $form['options']['index_directly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Index items immediately'),
      '#description' => $this->t('Immediately index new or updated items instead of waiting for the next cron run. This might have serious performance drawbacks and is generally not advised for larger sites.'),
      '#default_value' => $index->getOption('index_directly'),
    );
    // Build the cron limit element.
    $form['options']['cron_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cron batch size'),
      '#description' => $this->t('Set how many items will be indexed at once when indexing items during a cron run. "0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
      '#default_value' => $index->getOption('cron_limit'),
      '#size' => 4,
      '#states' => array(
        'invisible' => array(
          ':input[name="options[index_directly]"]' => array('checked' => TRUE),
        ),
      ),
    );
  }

  /**
   * Build the datasource configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Server\ServerInterface $server
   *   An instance of ServerInterface.
   */
  public function buildDatasourceConfigForm(array &$form, array &$form_state, IndexInterface $index) {
    // Build the datasource plugin configuration container element.
    $form['datasourcePluginConfig'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-datasource-config-form',
      ),
      '#tree' => TRUE,
    );
    // Check if the index has a valid datasource configured.
    if ($index->hasValidDatasource()) {
      // Get the datasource.
      $datasource = $index->getDatasource();
      // Get the datasource plugin definition.
      $datasource_plugin_definition = $datasource->getPluginDefinition();
      // Build the datasource configuration form.
      if (($datasource_plugin_config_form = $datasource->buildConfigurationForm(array(), $form_state))) {
        // Modify the datasource plugin configuration container element.
        $form['datasourcePluginConfig']['#type'] = 'details';
        $form['datasourcePluginConfig']['#title'] = $this->t('Configure @plugin', array('@plugin' => $datasource_plugin_definition['name']));
        $form['datasourcePluginConfig']['#description'] = String::checkPlain($datasource_plugin_definition['description']);
        $form['datasourcePluginConfig']['#collapsed'] = !$index->isNew();
        // Attach the build datasource plugin configuration form.
        $form['datasourcePluginConfig'] += $datasource_plugin_config_form;
      }
    }
    // Do not notify the user about a missing datasource plugin if a new index
    // is being configured.
    elseif (!$index->isNew()) {
      // Notify the user about the missing datasource plugin.
      drupal_set_message($this->t('The datasource plugin is missing or invalid.'), 'error');
    }
  }

  /**
   * Build the datasource plugin configuration form in context of an Ajax
   * request.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the structure of the form.
   */
  public function buildAjaxDatasourceConfigForm(array $form, array &$form_state) {
    // Get the datasource plugin configuration form.
    return $form['datasourcePluginConfig'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // Perform default entity form validate.
    parent::validate($form, $form_state);
    // Get the entity.
    $entity = $this->getEntity();
    // Get the current datasource plugin ID.
    $datasource_plugin_id = $entity->hasValidDatasource() ? $entity->getDatasource()->getPluginId() : NULL;
    // Check if the datasource plugin changed.
    if ($datasource_plugin_id !== $form_state['values']['datasourcePluginId']) {
      // Check if the datasource plugin configuration form input values exist.
      if (!empty($form_state['input']['datasourcePluginConfig'])) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        $form_state['input']['datasourcePluginConfig'] = array();
      }
      // Check if the datasource plugin configuration form values exist.
      if (!empty($form_state['values']['datasourcePluginConfig'])) {
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        $form_state['values']['datasourcePluginConfig'] = array();
      }
    }
    // Check if the entity has a valid datasource plugin.
    elseif ($entity->hasValidDatasource()) {
      // Validate the datasource plugin configuration form.
      $entity->getDatasource()->validateConfigurationForm($form['datasourcePluginConfig'], $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    // Get the entity.
    $entity = $this->getEntity();
    // Get the current datasource plugin ID.
    $datasource_plugin_id = $entity->hasValidDatasource() ? $entity->getDatasource()->getPluginId() : NULL;
    // Perform default entity form submittion.
    $entity = parent::submit($form, $form_state);
    // Check if the datasource plugin changed.
    if ($datasource_plugin_id !== $form_state['values']['datasourcePluginId']) {
      // Notify the user about the datasource configuration change.
      drupal_set_message($this->t('Please configure the used datasource.'), 'warning');
      // Rebuild the form.
      $form_state['rebuild'] = TRUE;
    }
    // Check if the entity has a valid datasource plugin.
    elseif ($entity->hasValidDatasource()) {
      // Get the datasource from the entity.
      $datasource = $entity->getDatasource();
      // Submit the datasource plugin configuration form.
      $datasource->submitConfigurationForm($form['datasourcePluginConfig'], $form_state);
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
        drupal_set_message($this->t('The index was successfully saved.'));
        // Redirect to the index page.
        // Build the route parameters.
        // Redirect to the entity delete confirm page.
        $form_state['redirect_route'] = array(
          'route_name' => 'search_api.index_view',
          'route_parameters' => array(
            'search_api_index' => $this->getEntity()->id(),
          ),
        );
      }
      catch (\Exception $ex) {
        // Rebuild the form.
        $form_state['rebuild'] = TRUE;
        // Log the exception to the watchdog.
        watchdog_exception('Search API', $ex);
        // Notify the user that the save operation failed.
        drupal_set_message($this->t('The index could not be saved.'), 'error');
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
      'route_name' => 'search_api.index_delete',
      'route_parameters' => array(
        'search_api_index' => $entity->id(),
      ),
    );

  }

}
