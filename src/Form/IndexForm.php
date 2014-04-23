<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexForm.
 */

namespace Drupal\search_api\Form;

use Drupal\search_api\Exception\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManager;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Datasource\DatasourcePluginManager;
use Drupal\search_api\Tracker\TrackerPluginManager;
use Drupal\Component\Utility\String;

/**
 * Provides a form for the Index entity.
 */
class IndexForm extends EntityFormController {

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
    $form['#tree'] = TRUE;
    // Build the name element.
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $this->t('Enter the displayed name for the index.'),
      '#default_value' => $index->label(),
      '#required' => TRUE,
      '#weight' => 1,
    );
    // Build the machine name element.
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
    if (!empty($form_state['values']['datasourcePluginIds'])) {
      // Notify the user about the datasource configuration change.
      drupal_set_message($this->t('Please configure the used data type.'), 'warning');
    }

    // Check if the datasource plugin changed.
    if (!empty($form_state['values']['trackerPluginId'])) {
      // Notify the user about the tracker configuration change.
      drupal_set_message($this->t('Please configure the used tracker.'), 'warning');
    }

    // Attach the admin css
    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    $form['datasourcePluginIds'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data types'),
      '#description' => $this->t('Select one or more data type of items that will be stored in this index. E.g. should it be nodes content or files data. This setting cannot be changed afterwards.'),
      '#options' => $this->getDatasourcePluginManager()->getDefinitionLabels(),
      '#default_value' => $index->getDatasourceIds(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#disabled' => !$index->isNew(),
      '#ajax' => array(
        'trigger_as' => array('name' => 'datasourcepluginids_configure'),
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#weight' => 3,
    );

    // Build the datasource plugin configuration container element.
    $form['datasourcePluginConfigs'] = array(
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
      '#value' => t('Configure'),
      '#limit_validation_errors' => array(array('datasourcePluginIds')),
      '#submit' => array('\Drupal\search_api\Form\IndexForm::submitAjaxDatasourceConfigForm'),
      '#ajax' => array(
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
      ),
      '#weight' => 5,
      '#attributes' => array('class' => array('js-hide')),
    );

    // Build the datasource configuration form.
    $this->buildDatasourcesConfigForm($form, $form_state, $index);

    $form['trackerPluginId'] = array(
      '#type' => 'select',
      '#title' => $this->t('Tracker'),
      '#description' => $this->t('Select the type of tracker which should be used for keeping track of item changes. This setting cannot be changed afterwards.'),
      '#options' => $this->getTrackerPluginManager()->getDefinitionLabels(),
      '#default_value' => $index->hasValidTracker() ? $index->getTracker()->getPluginId() : NULL,
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
    );

    // Build the tracker plugin configuration container element.
    $form['trackerPluginConfig'] = array(
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
      '#value' => t('Configure'),
      '#limit_validation_errors' => array(array('trackerPluginId')),
      '#submit' => array('\Drupal\search_api\Form\IndexForm::submitAjaxTrackerConfigForm'),
      '#ajax' => array(
        'callback' => '\Drupal\search_api\Form\IndexForm::buildAjaxTrackerConfigForm',
        'wrapper' => 'search-api-tracker-config-form',
      ),
      '#weight' => 8,
      '#attributes' => array('class' => array('js-hide')),
    );

    // Build the tracker configuration form.
    $this->buildTrackerConfigForm($form, $form_state, $index);

    // Build the server machine name element.
    $form['serverMachineName'] = array(
      '#type' => 'select',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select the server this index should reside on. Index can not be enabled without connection to valid server.'),
      '#options' => array('' => $this->t('< No server >')) + $this->getServerOptions(),
      '#default_value' => $index->hasValidServer() ? $index->getServer()->id() : NULL,
      '#weight' => 9,
    );

    // Build the status element.
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Select if the index will be enabled. This will only take effect if the selected server is also enabled.'),
      '#default_value' => $index->status(),
      // Can't enable an index lying on a disabled server or no server at all.
      '#disabled' => !$index->status() && (!$index->hasValidServer() || !$index->getServer()->status()),
      '#states' => array(
        'invisible' => array(
          '[name="serverMachineName"]' => array('value' => '')
        ),
      ),
      '#weight' => 10,
    );

    // Build the description element.
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
      '#title' => t('Index options'),
      '#collapsed' => TRUE,
      '#weight' => 12,
    );

    // Build the read only element.
    $form['options']['readOnly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Read only'),
      '#description' => $this->t('Do not write to this index or track the status of items in this index.'),
      '#default_value' => $index->isReadOnly(),
      // changed #parents so this option is not saved into options
      '#parents' => array('readOnly'),
      '#weight' => 13,
    );
    // Build the index directly element.
    $form['options']['index_directly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Index items immediately'),
      '#description' => $this->t('Immediately index new or updated items instead of waiting for the next cron run. This might have serious performance drawbacks and is generally not advised for larger sites.'),
      '#default_value' => $index->getOption('index_directly'),
      '#weight' => 14,
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
  public function buildDatasourcesConfigForm(array &$form, array &$form_state, IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if ($datasource_plugin_config_form = $datasource->buildConfigurationForm(array(), $form_state)) {
        // Modify the datasource plugin configuration container element.
        $form['datasourcePluginConfigs'][$datasource_id]['#type'] = 'details';
        $form['datasourcePluginConfigs'][$datasource_id]['#title'] = $this->t('Configure data type: @datasource_label', array('@datasource_label' => $datasource->getPluginDefinition()['label']));
        $form['datasourcePluginConfigs'][$datasource_id]['#open'] = $index->isNew() ? TRUE : $index->isNew();

        // Attach the build datasource plugin configuration form.
        $form['datasourcePluginConfigs'][$datasource_id] += $datasource_plugin_config_form;
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
  public function buildTrackerConfigForm(array &$form, array &$form_state, IndexInterface $index) {
    // Check if the index has a valid tracker configured.
    if ($index->hasValidTracker()) {
      // Get the tracker.
      $tracker = $index->getTracker();
      // Get the tracker plugin definition.
      $tracker_plugin_definition = $tracker->getPluginDefinition();
      // Build the tracker configuration form.
      if (($tracker_plugin_config_form = $tracker->buildConfigurationForm(array(), $form_state))) {
        // Modify the tracker plugin configuration container element.
        $form['trackerPluginConfig']['#type'] = 'details';
        $form['trackerPluginConfig']['#title'] = $this->t('Configure @plugin', array('@plugin' => $tracker_plugin_definition['label']));
        $form['trackerPluginConfig']['#description'] = String::checkPlain($tracker_plugin_definition['description']);
        $form['trackerPluginConfig']['#open'] = $index->isNew() ? TRUE : $index->isNew();

        // Attach the build tracker plugin configuration form.
        $form['trackerPluginConfig'] += $tracker_plugin_config_form;
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
  public static function buildAjaxDatasourceConfigForm(array $form, array &$form_state) {
    // Get the datasource plugin configuration form.
    return $form['datasourcePluginConfigs'];
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
  public static function buildAjaxTrackerConfigForm(array $form, array &$form_state) {
    // Get the tracker plugin configuration form.
    return $form['trackerPluginConfig'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    // Perform default entity form validate.
    parent::validate($form, $form_state);

    /** @var $entity \Drupal\search_api\Index\IndexInterface */
    $entity = $this->getEntity();

    // Check if the datasource plugin changed.
    if (!$entity->isNew() && ($entity->getDatasourceIds() !== array_keys($form_state['values']['datasourcePluginIds']))) {
      foreach ($form_state['values']['datasourcePluginIds'] as $datasource_id) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        if (!empty($form_state['input']['datasourcePluginConfigs'][$datasource_id])) {
          $form_state['input']['datasourcePluginConfigs'][$datasource_id] = array();
        }
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        if (!empty($form_state['values']['datasourcePluginConfigs'][$datasource_id])) {
          $form_state['values']['datasourcePluginConfigs'][$datasource_id] = array();
        }
      }
    }
    else {
      foreach ($form_state['values']['datasourcePluginIds'] as $datasource_id) {
        // Validate the datasource plugin configuration form.
        $datasource_form_state['values'] = isset($form_state['values']['datasourcePluginConfigs'][$datasource_id]) ? $form_state['values']['datasourcePluginConfigs'][$datasource_id] : array();
        $entity->getDatasource($datasource_id)->validateConfigurationForm($form['datasourcePluginConfigs'][$datasource_id], $datasource_form_state);
      }
    }

    // Get the current tracker plugin ID.
    $tracker_plugin_id = $entity->hasValidTracker() ? $entity->getTracker()->getPluginId() : NULL;
    // Check if the tracker plugin changed.
    if ($tracker_plugin_id !== $form_state['values']['trackerPluginId']) {
      // Check if the tracker plugin configuration form input values exist.
      if (!empty($form_state['input']['trackerPluginConfig'])) {
        // Overwrite the plugin configuration form input values with an empty
        // array. This will force the Drupal Form API to use the default values.
        $form_state['input']['trackerPluginConfig'] = array();
      }
      // Check if the tracker plugin configuration form values exist.
      if (!empty($form_state['values']['trackerPluginConfig'])) {
        // Overwrite the plugin configuration form values with an empty array.
        // This has no effect on the Drupal Form API but is done to keep the
        // data consistent.
        $form_state['values']['trackerPluginConfig'] = array();
      }
    }
    // Check if the entity has a valid tracker plugin.
    elseif ($entity->hasValidTracker()) {
      // Build the tracker plugin configuration form state.
      $tracker_form_state = array();
      if (!empty($form_state['values']['trackerPluginConfig'])) {
        $tracker_form_state['values'] = $form_state['values']['trackerPluginConfig'];
      }

      // Validate the tracker plugin configuration form.
      $entity->getTracker()->validateConfigurationForm($form['trackerPluginConfig'], $tracker_form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    /** @var $entity \Drupal\search_api\Index\IndexInterface */
    $entity = $this->getEntity();

    // Perform default entity form submission.
    // merge the fields stored as options from the field form so they do not get lost during save
    $form_state['values']['options'] = array_merge($entity->getOptions(), $form_state['values']['options']);

    // When creating the index, we add the default field configuration coming
    // from the entity type object.
    if ($entity->isNew()) {
      //$entity->options['fields'] = $entity->getDatasource()->getEntityType()->get('search_api_default_fields');
    }

    foreach ($entity->getDatasourceIds() as $datasource_id) {
      // Build the datasource plugin configuration form state.
      $datasource_form_state = array(
        'values' => $form_state['values']['datasourcePluginConfigs'][$datasource_id],
      );
      // Also add all additional values so that we can read them in the plugin.
      // Useful for the status
      $datasource_form_state['values']['index'] = $form_state['values'];
      // Remove the datasource plugin configuration from the index form state
      // as it is already provided.
      unset($datasource_form_state['values']['index']['datasourcePluginConfigs'][$datasource_id]);
      // Submit the datasource plugin configuration form.
      $entity->getDatasource($datasource_id)->submitConfigurationForm($form['datasourcePluginConfigs'][$datasource_id], $datasource_form_state);
    }

    // Check if the entity has a valid tracker plugin.
    if ($entity->hasValidTracker()) {
      // Build the tracker plugin configuration form state.
      $tracker_form_state = array();
      if (!empty($form_state['values']['trackerPluginConfig'])) {
        $tracker_form_state['values'] = $form_state['values']['trackerPluginConfig'];
      }

      // Also add all additional values so that we can read them in the plugin.
      // Useful for the status.
      $tracker_form_state['values']['index'] = $form_state['values'];
      // Remove the tracker plugin configuration from the index form state
      // as it is already provided.
      unset($tracker_form_state['values']['index']['trackerPluginConfig']);
      // Submit the tracker plugin configuration form.
      $entity->getTracker()->submitConfigurationForm($form['trackerPluginConfig'], $tracker_form_state);
    }

    return parent::submit($form, $form_state);
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
