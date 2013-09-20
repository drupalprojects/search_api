<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\IndexFormController.
 */

namespace Drupal\search_api\Controller;

/*
 * Include required classes and interfaces.
 */
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
      $options[$plugin_id] = String::checkPlain($this->t($plugin_definition['name']));
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
    // Iterate through the service plugin definitions.
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
    // Build the default entity form.
    $form = parent::form($form, $form_state);
    // Get the entity and attach to the form state.
    $entity = $form_state['entity'] = $this->getEntity();
    // Check if the entity is being created.
    if ($entity->isNew()) {
      // Change the page title to 'Add index'.
      drupal_set_title($this->t('Add search index'));
    }
    else {
      // Change the page title to 'Edit search index @label'.
      drupal_set_title($this->t('Edit search index @label', array('@label' => $entity->label())));
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
    // Build the datasource element.
    $form['datasourcePluginId'] = array(
      '#type' => 'select',
      '#title' => $this->t('Datasource'),
      '#description' => $this->t('Select the datasource of items that will be indexed in this index. This setting cannot be changed afterwards.'),
      '#options' => $this->getDatasourcePluginDefinitionOptions(),
      '#default_value' => $index->hasValidDatasource() ? $index->getDatasource()->getPluginId() : NULL,
      '#required' => TRUE,
      '#disabled' => !$index->isNew(),
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
    // Build the description element.
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the index.'),
      '#default_value' => $index->getDescription(),
    );
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
    $form['options'] = array('#tree' => TRUE);
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
   * {@inheritdoc}
   */
  public function save(array $form, array &$form_state) {
    // Catch any exception that may get thrown during save operation.
    try {
      // Save changes made to the entity.
      $this->getEntity()->save();
      // Notify the user that the server was created.
      drupal_set_message($this->t('The index was successfully saved.'));
      // Redirect to the server page.
      $form_state['redirect'] = $this->url('search_api.index_overview');
    }
    catch (Exception $ex) {
      // Rebuild the form.
      $form_state['rebuild'] = TRUE;
      // Log the exception to the watchdog.
      watchdog_exception('Search API', $ex);
      // Notify the user that the save operation failed.
      drupal_set_message($this->t('The index could not be saved.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    // Get the entity.
    $entity = $this->getEntity();
    // Build the route parameters.
    $params = array('search_api_index' => $entity->id());
    // Redirect to the entity delete confirm page.
    $form_state['redirect'] = $this->url('search_api.index_delete', $params);
  }

}
