<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Datasource\DatasourcePluginManager;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Tracker\TrackerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the Index entity.
 */
class IndexForm extends EntityForm {

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
   * The Search API tracker plugin manager.
   *
   * This object members must be set to anything other than private in order
   * for \Drupal\Core\DependencyInjection\DependencySerialization to detect.
   *
   * @var \Drupal\search_api\Tracker\TrackerPluginManager
   */
  protected $trackerPluginManager;

  /**
   * Create an IndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager
   *   The search datasource plugin manager.
   * @param \Drupal\search_api\Tracker\TrackerPluginManager $tracker_plugin_manager
   *   The Search API tracker plugin manager.
   */
  public function __construct(EntityManager $entity_manager, DatasourcePluginManager $datasource_plugin_manager, TrackerPluginManager $tracker_plugin_manager) {
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->datasourcePluginManager = $datasource_plugin_manager;
    $this->trackerPluginManager = $tracker_plugin_manager;
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
   * Get the Search API tracker plugin manager.
   *
   * @return \Drupal\search_api\Tracker\TrackerPluginManager
   *   An instance of TrackerPluginManager.
   */
  protected function getTrackerPluginManager() {
    return $this->trackerPluginManager;
  }

  /**
   * Get the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   */
  protected function getIndexStorage() {
    return $this->getEntityManager()->getStorage('search_api_index');
  }

  /**
   * Get the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   An instance of EntityStorageInterface.
   */
  protected function getServerStorage() {
    return $this->getEntityManager()->getStorage('search_api_server');
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
    foreach ($this->getServerStorage()->loadMultiple() as $server_machine_name => $server) {
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
      $container->get('plugin.manager.search_api.datasource'),
      $container->get('plugin.manager.search_api.tracker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
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
  public function buildEntityForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    $form['#tree'] = TRUE;
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $this->t('Enter the displayed name for the index.'),
      '#default_value' => $index->label(),
      '#required' => TRUE,
      '#weight' => 1,
    );
    $form['machine_name'] = array(
      '#type' => 'machine_name',
      '#default_value' => $index->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => array(
        'exists' => array($this->getIndexStorage(), 'load'),
        'source' => array('name'),
      ),
      '#weight' => 2,
    );

    // Check if the datasource plugin changed.
    if (!empty($form_state['values']['datasources'])) {
      // Notify the user about the datasource configuration change.
      drupal_set_message($this->t('Please configure the used data type.'), 'warning');
    }

    // Check if the tracker plugin changed.
    if (!empty($form_state['values']['tracker'])) {
      // Notify the user about the tracker configuration change.
      drupal_set_message($this->t('Please configure the used tracker.'), 'warning');
    }

    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    $form['datasources'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data types'),
      '#description' => $this->t('Select one or more data type of items that will be stored in this index. E.g. should it be nodes content or files data.'),
      '#options' => $this->getDatasourcePluginManager()->getDefinitionLabels(),
      '#default_value' => $index->getDatasourceIds(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#ajax' => array(
        'trigger_as' => array('name' => 'datasourcepluginids_configure'),
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#weight' => 3,
    );

    $form['datasource_configs'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-datasources-config-form',
      ),
      '#weight' => 4,
      '#tree' => TRUE,
    );

    $form['datasourceConfigureButton'] = array(
      '#type' => 'submit',
      '#name' => 'datasourcepluginids_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('datasources')),
      '#submit' => array('\Drupal\search_api\Form\IndexForm::submitAjaxDatasourceConfigForm'),
      '#ajax' => array(
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
      ),
      '#weight' => 5,
      '#attributes' => array('class' => array('js-hide')),
    );

    $this->buildDatasourcesConfigForm($form, $form_state, $index);

    $tracker_options = $this->getTrackerPluginManager()->getDefinitionLabels();
    $form['tracker'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Tracker'),
      '#description' => $this->t('Select the type of tracker which should be used for keeping track of item changes.'),
      '#options' => $this->getTrackerPluginManager()->getDefinitionLabels(),
      '#default_value' => $index->hasValidTracker() ? $index->getTracker()->getPluginId() : key($tracker_options),
      '#required' => TRUE,
      '#disabled' => !$index->isNew(),
      '#ajax' => array(
        'trigger_as' => array('name' => 'trackerpluginid_configure'),
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxTrackerConfigForm',
        'wrapper' => 'search-api-tracker-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#weight' => 6,
      '#access' => count($tracker_options) > 1,
    );

    $form['tracker_config'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-tracker-config-form',
      ),
      '#weight' => 7,
      '#tree' => TRUE,
    );

    $form['trackerConfigureButton'] = array(
      '#type' => 'submit',
      '#name' => 'trackerpluginid_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('tracker')),
      '#submit' => array('\Drupal\search_api\Form\IndexForm::submitAjaxTrackerConfigForm'),
      '#ajax' => array(
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxTrackerConfigForm',
        'wrapper' => 'search-api-tracker-config-form',
      ),
      '#weight' => 8,
      '#attributes' => array('class' => array('js-hide')),
    );

    $this->buildTrackerConfigForm($form, $form_state, $index);

    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select the server this index should reside on. Index can not be enabled without connection to valid server.'),
      '#options' => array('' => $this->t('- No server -')) + $this->getServerOptions(),
      '#default_value' => $index->hasValidServer() ? $index->getServerId() : NULL,
      '#weight' => 9,
    );

    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Select if the index will be enabled. This will only take effect if the selected server is also enabled.'),
      '#default_value' => $index->status(),
      // Can't enable an index lying on a disabled server or no server at all.
      '#disabled' => !$index->status() && (!$index->hasValidServer() || !$index->getServer()->status()),
      '#states' => array(
        'invisible' => array(
          '[name="server"]' => array('value' => '')
        ),
      ),
      '#weight' => 10,
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the index.'),
      '#default_value' => $index->getDescription(),
      '#weight' => 11,
    );

    $form['options'] = array(
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => $this->t('Index options'),
      '#collapsed' => TRUE,
      '#weight' => 12,
    );

    // We display the "read-only" flag along with the other option, even though
    // it is a property directly on the index object. We use "#parents" to move
    // it to the correct place in the form values.
    $form['options']['read_only'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Read only'),
      '#description' => $this->t('Do not write to this index or track the status of items in this index.'),
      '#default_value' => $index->isReadOnly(),
      '#parents' => array('read_only'),
      '#weight' => 13,
    );
    $form['options']['index_directly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Index items immediately'),
      '#description' => $this->t('Immediately index new or updated items instead of waiting for the next cron run. This might have serious performance drawbacks and is generally not advised for larger sites.'),
      '#default_value' => $index->getOption('index_directly'),
      '#weight' => 14,
    );
    $form['options']['cron_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cron batch size'),
      '#description' => $this->t('Set how many items will be indexed at once when indexing items during a cron run. "0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
      '#default_value' => $index->getOption('cron_limit'),
      '#size' => 4,
      '#weight' => 15,
    );
  }


  /**
   * Builds the datasource configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   An instance of IndexInterface.
   */
  public function buildDatasourcesConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if ($datasource_plugin_config_form = $datasource->buildConfigurationForm(array(), $form_state)) {
        // Modify the datasource plugin configuration container element.
        $form['datasource_configs'][$datasource_id]['#type'] = 'details';
        $form['datasource_configs'][$datasource_id]['#title'] = $this->t('Configure data type: @datasource_label', array('@datasource_label' => $datasource->getPluginDefinition()['label']));
        $form['datasource_configs'][$datasource_id]['#open'] = $index->isNew() ? TRUE : $index->isNew();

        // Attach the build datasource plugin configuration form.
        $form['datasource_configs'][$datasource_id] += $datasource_plugin_config_form;
      }
    }
  }

  /**
   * Build the tracker configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\search_api\Index\IndexInterface $server
   *   An instance of IndexInterface.
   */
  public function buildTrackerConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    // Check if the index has a valid tracker configured.
    if ($index->hasValidTracker()) {
      // Get the tracker.
      $tracker = $index->getTracker();
      // Get the tracker plugin definition.
      $tracker_plugin_definition = $tracker->getPluginDefinition();
      // Build the tracker configuration form.
      if (($tracker_plugin_config_form = $tracker->buildConfigurationForm(array(), $form_state))) {
        // Modify the tracker plugin configuration container element.
        $form['tracker_config']['#type'] = 'details';
        $form['tracker_config']['#title'] = $this->t('Configure @plugin', array('@plugin' => $tracker_plugin_definition['label']));
        $form['tracker_config']['#description'] = String::checkPlain($tracker_plugin_definition['description']);
        $form['tracker_config']['#open'] = $index->isNew() ? TRUE : $index->isNew();

        // Attach the build tracker plugin configuration form.
        $form['tracker_config'] += $tracker_plugin_config_form;
      }
    }
    // Do not notify the user about a missing tracker plugin if a new index
    // is being configured.
    elseif (!$index->isNew()) {
      // Notify the user about the missing tracker plugin.
      drupal_set_message($this->t('The tracker plugin is missing or invalid.'), 'error');
    }
  }

  /**
   * Button submit handler for datasource configure button 'datasource_configure' button.
   */
  public static function submitAjaxDatasourceConfigForm($form, &$form_state) {
    $form_state['rebuild'] = TRUE;
  }

  /**
   * Button submit handler for tracker configure button 'tracker_configure' button.
   */
  public static function submitAjaxTrackerConfigForm($form, &$form_state) {
    $form_state['rebuild'] = TRUE;
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
  public static function buildAjaxDatasourceConfigForm(array $form, FormStateInterface $form_state) {
    // Get the datasource plugin configuration form.
    return $form['datasource_configs'];
  }

  /**
   * Build the tracker plugin configuration form in context of an Ajax request.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the structure of the form.
   */
  public static function buildAjaxTrackerConfigForm(array $form, FormStateInterface $form_state) {
    // Get the tracker plugin configuration form.
    return $form['tracker_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    // Perform default entity form validate.
    parent::validate($form, $form_state);

    /** @var $entity \Drupal\search_api\Index\IndexInterface */
    $entity = $this->getEntity();

    // Store the array of datasource plugin IDs with integer keys.
    $form_state['values']['datasources'] = array_values($form_state['values']['datasources']);

    // Call validateConfigurationForm() for each enabled datasource.
    $form_state['datasourcePlugins'] = array();
    foreach ($form_state['values']['datasources'] as $datasource_id) {
      // Avoid notices if the datasource has no configuration form.
      if (!isset($form_state['values']['datasource_configs'][$datasource_id])) {
        continue;
      }
      if ($entity->isValidDatasource($datasource_id)) {
        $datasource = $entity->getDatasource($datasource_id);
      }
      else {
        $config = array('index' => $entity);
        $datasource = $this->datasourcePluginManager->createInstance($datasource_id, $config);
      }
      $form_state['datasourcePlugins'][$datasource_id] = $datasource;
      $datasource_form_state = new FormState();
      $datasource_form_state['values'] = $form_state['values']['datasource_configs'][$datasource_id];
      $datasource_form = (isset($form['datasource_configs'][$datasource_id])) ? $form['datasource_configs'][$datasource_id] : array();
      $datasource->validateConfigurationForm($datasource_form, $datasource_form_state);
      unset($datasource_form_state);
    }

    // Get the current tracker plugin ID.
    $tracker_plugin_id = $entity->hasValidTracker() ? $entity->getTracker()->getPluginId() : NULL;
    // Check if the tracker plugin changed.
    if ($tracker_plugin_id !== $form_state['values']['tracker']) {
      // Check if the tracker plugin configuration form input values exist.
      if (!empty($form_state['input']['tracker_config'])) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        $form_state['input']['tracker_config'] = array();
      }
      // Check if the tracker plugin configuration form values exist.
      if (!empty($form_state['values']['tracker_config'])) {
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        $form_state['values']['tracker_config'] = array();
      }
    }
    // Check if the entity has a valid tracker plugin.
    elseif ($entity->hasValidTracker()) {
      // Build the tracker plugin configuration form state.
      $tracker_form_state = new FormState(array('values' => array()));
      if (!empty($form_state['values']['tracker_config'])) {
        $tracker_form_state['values'] = $form_state['values']['tracker_config'];
      }

      // Validate the tracker plugin configuration form.
      $entity->getTracker()->validateConfigurationForm($form['tracker_config'], $tracker_form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    /** @var $entity \Drupal\search_api\Index\IndexInterface */
    $entity = $this->getEntity();

    // Perform default entity form submission.
    // merge the fields stored as options from the field form so they do not get lost during save
    $form_state['values']['options'] = array_merge($entity->getOptions(), $form_state['values']['options']);

    foreach ($entity->getDatasourceIds() as $datasource_id) {
      // Build the datasource plugin configuration form state.
      if (isset($form_state['values']['datasource_configs'][$datasource_id])) {
        $datasource_form_state = new FormState();
        $datasource_form_state['values'] = $form_state['values']['datasource_configs'][$datasource_id];
        // Also add all additional values so that we can read them in the plugin.
        // Useful for the status
        $datasource_form_state['values']['index'] = $form_state['values'];
        // Remove the datasource plugin configuration from the index form state
        // as it is already provided.
        unset($datasource_form_state['values']['index']['datasource_configs'][$datasource_id]);
        // Submit the datasource plugin configuration form.
        try {
          $entity->getDatasource($datasource_id)->submitConfigurationForm($form['datasource_configs'][$datasource_id], $datasource_form_state);
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
    }

    // Check if the entity has a valid tracker plugin.
    if ($entity->hasValidTracker()) {
      // Build the tracker plugin configuration form state.
      $tracker_form_state = new FormState();
      if (!empty($form_state['values']['tracker_config'])) {
        $tracker_form_state['values'] = $form_state['values']['tracker_config'];
      }

      // Also add all additional values so that we can read them in the plugin.
      // Useful for the status.
      $tracker_form_state['values']['index'] = $form_state['values'];
      // Remove the tracker plugin configuration from the index form state
      // as it is already provided.
      unset($tracker_form_state['values']['index']['tracker_config']);
      // Submit the tracker plugin configuration form.
      $entity->getTracker()->submitConfigurationForm($form['tracker_config'], $tracker_form_state);
    }

    return parent::submit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
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
  public function delete(array $form, FormStateInterface $form_state) {
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
