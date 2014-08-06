<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\search_api\Datasource\DatasourcePluginManager;
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
    // Check if the form is being rebuilt.
    if ($form_state->get('rebuild')) {
      // Rebuild the entity with the form state values.
      $this->entity = $this->buildEntity($form, $form_state);
    }
    // Build the default entity form.
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $entity = $this->getEntity();
    $form_state->set('entity', $entity);
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
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
    $values = $form_state->getValues();
    if (!empty($values['datasources'])) {
      // Notify the user about the datasource configuration change.
      drupal_set_message($this->t('Please configure the used data type.'), 'warning');
    }

    // Check if the tracker plugin changed.
    if (!empty($values['tracker'])) {
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\search_api\Index\IndexInterface index
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
  public static function submitAjaxDatasourceConfigForm($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Button submit handler for tracker configure button 'tracker_configure' button.
   */
  public static function submitAjaxTrackerConfigForm($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Build the datasource plugin configuration form in context of an Ajax
   * request.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
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
    $values = $form_state->getValues();
    $datasource_ids = array_values($values['datasources']);
    $form_state->addValue('datasources', $datasource_ids);

    // Call validateConfigurationForm() for each enabled datasource.
    $datasource_plugins = array();
    $datasource_forms = array();
    $datasource_form_states = array();
    foreach ($datasource_ids as $datasource_id) {
      if ($entity->isValidDatasource($datasource_id)) {
        $datasource = $entity->getDatasource($datasource_id);
      }
      else {
        $datasource = $this->datasourcePluginManager->createInstance($datasource_id, array('index' => $entity));
      }
      $datasource_plugins[$datasource_id] = $datasource;
      $datasource_forms[$datasource_id] = array();
      if (!empty($form['datasource_configs'][$datasource_id])) {
        $datasource_forms[$datasource_id] = & $form['datasource_configs'][$datasource_id];
      }
      $datasource_form_states[$datasource_id] = new SubFormState($form_state, array('datasource_configs', $datasource_id));
      $datasource->validateConfigurationForm($datasource_forms[$datasource_id], $datasource_form_states[$datasource_id]);
    }
    $form_state->set('datasource_plugins', $datasource_plugins);
    $form_state->set('datasource_forms', $datasource_forms);
    $form_state->set('datasource_form_states', $datasource_form_states);

    // Call validateConfigurationForm() for the (possibly new) tracker.
    $tracker_id = $values['tracker'];
    if ($entity->getTrackerId() == $tracker_id) {
      $tracker = $entity->getTracker();
    }
    else {
      $tracker = $this->trackerPluginManager->createInstance($tracker_id, array('index' => $entity));
    }
    $tracker_form_state = new SubFormState($form_state, array('tracker_config'));
    $tracker->validateConfigurationForm($form['tracker_config'], $tracker_form_state);
    $form_state->set('tracker_plugin', $tracker);
    $form_state->set('tracker_form_state', $tracker_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    /** @var $entity \Drupal\search_api\Index\IndexInterface */
    $entity = $this->getEntity();

    // @todo Redirect to a confirm form if changing server, since that isn't
    //   such a light operation (equaling a "clear", basically).

    $form_state->addValue('options', array_merge($entity->getOptions(), $form_state->getValues()['options']));

    $datasource_forms = $form_state->get('datasource_forms');
    $datasource_form_states = $form_state->get('datasource_form_states');
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    foreach ($form_state->get('datasource_plugins') as $datasource_id => $datasource) {
      $datasource->submitConfigurationForm($datasource_forms[$datasource_id], $datasource_form_states[$datasource_id]);
    }

    /** @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $tracker = $form_state->get('tracker_plugin');
    $tracker_form_state = $form_state->get('tracker_form_state');
    $tracker->validateConfigurationForm($form['tracker_config'], $tracker_form_state);

    return parent::submit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Check if the form does not need to be rebuild.
    if (!$form_state->get('rebuild')) {
      // Catch any exception that may get thrown during save operation.
      try {
        // Save changes made to the entity.
        $this->getEntity()->save();
        // Notify the user that the server was created.
        drupal_set_message($this->t('The index was successfully saved.'));
        // Redirect to the index page.
        $form_state->setRedirect(new Url('search_api.index_view', array('search_api_index' => $this->getEntity()->id())));
      }
      catch (\Exception $ex) {
        // Rebuild the form.
        $form_state->setRebuild();
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
    // Redirect to the entity delete confirm page.
    $form_state->setRedirect(new Url('search_api.index_delete', array('search_api_index' => $this->getEntity()->id())));
  }

}
