<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersFormController.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Element;

/**
 * Provides a filters form for the Index entity.
 */
class IndexFiltersForm extends EntityFormController {

  /**
   * The index being configured.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The datasource manager.
   *
   * @var \Drupal\search_api\Processor\ProcessorPluginManager
   */
  protected $processorPluginManager;

  /**
   * Constructs a ContentEntityFormController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\search_api\Processor\ProcessorPluginManager $processor_plugin_manager
   *   The processor plugin manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, ProcessorPluginManager $processor_plugin_manager) {
    $this->entityManager = $entity_manager;
    $this->processorPluginManager = $processor_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('search_api.processor.plugin.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, array &$form_state) {
    // Fetch all the processor plugins
    $processor_info = $this->processorPluginManager->getDefinitions();

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'search_api/drupal.search_api.index-active-formatters';

    // Set a proper title
    $form['#title'] = $this->t('Manage filters for search index @label', array('@label' => $this->entity->label()));

    // Processors
    $processors = $this->entity->getOption('processors');
    $processor_objects = isset($form_state['processors']) ? $form_state['processors'] : array();
    $internal_weight = 0;

    foreach ($processor_info as $name => $processor) {
      if (!isset($processors[$name])) {
        $processors[$name]['status'] = 0;
        $processors[$name]['weight'] = 0;
      }

      // Add an internal used weight, to ensure the order of the order of the
      // processors.
      $processors[$name]['_internal_weight'] = $internal_weight;
      $internal_weight++;

      $settings = empty($processors[$name]['settings']) ? array() : $processors[$name]['settings'];
      $settings['index'] = $this->entity;

      if (empty($processor_objects[$name]) && class_exists($processor['class'])) {
        $processor_objects[$name] = $this->processorPluginManager->createInstance($name, $settings);
      }

      if (!(class_exists($processor['class']) && $processor_objects[$name] instanceof ProcessorInterface)) {
        watchdog('search_api', t('Processor @id specifies illegal processor class @class.', array('@id' => $name, '@class' => $processor['class'])), NULL, WATCHDOG_WARNING);
        unset($processor_info[$name]);
        unset($processors[$name]);
        unset($processor_objects[$name]);
        continue;
      }
      if (!$processor_objects[$name]->supportsIndex($this->entity)) {
        unset($processor_info[$name]);
        unset($processors[$name]);
        unset($processor_objects[$name]);
        continue;
      }
    }

    $form_state['processors'] = $processor_objects;
    $form['#processors'] = $processors;
    $form['processors'] = array(
      '#type' => 'details',
      '#title' => t('Processors'),
      '#description' => t('Select processors which will pre- and post-process data at index and search time, and their order. ' .
        'Most processors will only influence fulltext fields, but refer to their individual descriptions for details regarding their effect.'),
      '#open' => TRUE,
    );

    // Processor status.
    $form['processors']['status'] = array(
      '#type' => 'item',
      '#title' => t('Enabled processors'),
      '#prefix' => '<div class="search-api-status-wrapper">',
      '#suffix' => '</div>',
    );

    foreach ($processor_info as $name => $processor) {
      $form['processors']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $processor['label'],
        '#default_value' => $processors[$name]['status'],
        '#parents' => array('processors', $name, 'status'),
        '#description' => $processor['description'],
        '#weight' => $processors[$name]['_internal_weight'],
      );
    }

    $form['processors']['order'] = array(
      '#type' => 'table',
      '#header' => array(t('Processor'), t('Weight')),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'search-api-processor-weight'
        ),
      ),
    );

    // Currently the #weight of a row doesn't work, but should (see the
    // documentation of the drupal_pre_render_table() function.
    // So we sort the rows manually (bohoo bohoo).
    $processor_info_sorted = $processor_info;
    foreach($processor_info_sorted as $name => &$processor) {
      $processor['#weight'] = $processors[$name]['weight'];
    }
    uasort($processor_info_sorted, 'Drupal\Component\Utility\SortArray::sortByWeightProperty');

    foreach ($processor_info_sorted as $name => $processor_sorted) {

      $form['processors']['order'][$name]['#attributes']['class'][] = 'draggable';
      $form['processors']['order'][$name]['label'] = array(
        '#markup' => String::checkPlain($processor_sorted['label']),
      );

      // Temporarily disabled (see comment above).
      // $form['processors']['order'][$name]['#weight'] = $processors[$name]['weight'];

      // TableDrag: Weight column element.
      $form['processors']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $processor_sorted['label'])),
        '#title_display' => 'invisible',
        '#default_value' => $processors[$name]['weight'],
        '#parents' => array('processors', $name, 'weight'),
        '#attributes' => array('class' => array('search-api-processor-weight')),
      );
    }

    // Processor settings.
    $form['processor_settings'] = array(
      '#title' => t('Processor settings'),
      '#type' => 'vertical_tabs',
    );

    foreach ($processor_info as $name => $processor) {
      /** @var $processor_plugin \Drupal\search_api\Processor\ProcessorInterface */
      $processor_plugin = $processor_objects[$name];
      $settings_form = $processor_plugin->buildConfigurationForm($form, $form_state);

      if (!empty($settings_form)) {
        $form['processors']['settings'][$name] = array(
          '#type' => 'details',
          '#title' => $processor['label'],
          '#group' => 'processor_settings',
          '#weight' => $processors[$name]['_internal_weight'],
          '#parents' => array('processors', $name, 'settings'),
        );
        $form['processors']['settings'][$name] += $settings_form;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, array &$form_state) {
    foreach ($form_state['processors'] as $name => $processor) {
      if (isset($form['processors']['settings'][$name]) && !empty($form['processors']['settings'][$name]['status']) && isset($form_state['values']['processors'][$name]['settings'])) {
        /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
        $processor_form_state = $this->getFilterFormState($name, $form_state);
        $processor->validateConfigurationForm($form['processors']['settings'][$name], $processor_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $values = $form_state['values'];
    unset($values['processors']['settings']);
    unset($values['processors']['status']);
    unset($values['processors']['order']);

    $options = $this->entity->getOptions();

    // Store processor settings.
    foreach ($form_state['processors'] as $name => $processor) {
      $processor_form = isset($form['processors']['settings'][$name]) ? $form['processors']['settings'][$name] : array();

      if (!empty($form['processors']['settings'][$name]['status'])) {

        // We have to create our own form_state for the plugin form in order to
        // get it to save correctly. This ensures we can use submitConfigurationForm
        // in the correct manner, as described in
        $processor_form_state = $this->getFilterFormState($name, $form_state);
        //$processor->submitConfigurationForm($processor_form, $processor_form_state);
      }

      $values['processors'][$name] += array('settings' => array());
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $values['processors'][$name]['settings'] = $processor->getConfiguration();
    }

    if (!isset($options['processors']) || $options['processors'] !== $values['processors']) {
      // Save the already sorted arrays to avoid having to sort them at each use.
      uasort($values['processors'], array($this, 'elementCompare'));
      $this->entity->setOption('processors', $values['processors']);

      // Reset the index's internal property cache to correctly incorporate the
      // new data alterations.
      //$this->entity->resetCaches();

      $this->entity->save();
      //$this->entity->reindex();
      drupal_set_message(t("The indexing workflow was successfully edited. All content was scheduled for re-indexing so the new settings can take effect."));
    }
    else {
      drupal_set_message(t('No values were changed.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);

    // Remove the delete action
    unset($actions['delete']);

    return $actions;
  }

  /**
   * Sort callback sorting array elements by their "weight" key, if present.
   *
   * @see element_sort()
   */
  function elementCompare($a, $b) {
    $a_weight = (is_array($a) && isset($a['weight'])) ? $a['weight'] : 0;
    $b_weight = (is_array($b) && isset($b['weight'])) ? $b['weight'] : 0;
    if ($a_weight == $b_weight) {
      return 0;
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Returns the portion of the form_state array used in validate
   * and submit that corresponds to the filter being processed
   *
   * @param $filter_name Name of processor/filter
   * @param $form_state form_state array passed into validate,submit methods
   * @return array
   * @see submit
   * @see validate
   */
  protected function getFilterFormState($filter_name, $form_state) {
    $filter_form_state = array('values' => array());
    if(isset($form_state['values']['processors'][$filter_name]['settings'])) {
        $filter_form_state['values'] = $form_state['values']['processors'][$filter_name]['settings'];
    }
    return $filter_form_state;
  }

}
