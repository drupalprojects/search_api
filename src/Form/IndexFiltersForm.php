<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersFormController.
 */

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring the processors of a search index.
 */
class IndexFiltersForm extends EntityForm {

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
   * Constructs a IndexFiltersForm object.
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
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    /** @var \Drupal\search_api\Processor\ProcessorPluginManager $processor_plugin_manager */
    $processor_plugin_manager = $container->get('plugin.manager.search_api.processor');
    return new static($entity_manager, $processor_plugin_manager);
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
    $processors_by_weight = $this->entity->getProcessors(TRUE, 'weight');
    $processors_by_name = isset($form_state['processors']) ? $form_state['processors'] : $this->entity->getProcessors(TRUE, 'name');
    $processors_settings = $this->entity->getOption('processors');

    // Make sure that we have weights and status for all processors, even new
    // ones.
    foreach ($processors_by_name as $name => $processor) {
      $processors_settings[$name]['status'] = (!isset($processors_settings[$name]['status'])) ? 0 : $processors_settings[$name]['status'];
      $processors_settings[$name]['weight'] = (!isset($processors_settings[$name]['weight'])) ? 0 : $processors_settings[$name]['weight'];

      $settings = empty($processors_settings[$name]['settings']) ? array() : $processors_settings[$name]['settings'];
      $settings['index'] = $this->entity;
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'search_api/drupal.search_api.index-active-formatters';
    $form['#title'] = $this->t('Manage filters for search index @label', array('@label' => $this->entity->label()));

    $form_state['processors'] = $processors_by_name;
    $form['#processors'] = $processors_settings;
    $form['processors'] = array(
      '#type' => 'details',
      '#title' => $this->t('Processors'),
      '#description' => $this->t('Select processors which will pre- and post-process data at index and search time, and their order.'),
      '#open' => TRUE,
    );

    // Add the list of processors with checkboxes to enable/disable them.
    $form['processors']['status'] = array(
      '#type' => 'item',
      '#title' => $this->t('Enabled processors'),
      '#prefix' => '<div class="search-api-status-wrapper">',
      '#suffix' => '</div>',
    );

    foreach ($processors_by_name as $name => $processor) {
      $form['processors']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $processor->label(),
        '#default_value' => $processors_settings[$name]['status'],
        '#parents' => array('processors', $name, 'status'),
        '#description' => $processor->getPluginDefinition()['description'],
      );
    }

    // Add a tabledrag-enabled table to re-order the processors. Rows for
    // disabled processors are hidden with JS magic, but need to be included in
    // case the processor is enabled.
    $form['processors']['order'] = array(
      '#type' => 'table',
      '#header' => array($this->t('Processor'), $this->t('Weight')),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'search-api-processor-weight'
        ),
      ),
    );

    foreach ($processors_by_weight as $name => $processor) {
      $form['processors']['order'][$name]['#attributes']['class'][] = 'draggable';
      $form['processors']['order'][$name]['label'] = array(
        '#markup' => String::checkPlain($processor->label()),
      );

      // TableDrag: Weight column element.
      $form['processors']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', array('@title' => $processor->label())),
        '#title_display' => 'invisible',
        '#default_value' => $processors_settings[$name]['weight'],
        '#parents' => array('processors', $name, 'weight'),
        '#attributes' => array('class' => array('search-api-processor-weight')),
      );
    }

    // Add vertical tabs containing the settings for the processors. Tabs for
    // disabled processors are hidden with JS magic, but need to be included in
    // case the processor is enabled.
    $form['processor_settings'] = array(
      '#title' => $this->t('Processor settings'),
      '#type' => 'vertical_tabs',
    );

    foreach ($processors_by_weight as $name => $processor) {
      $settings_form = $processor->buildConfigurationForm($form, $form_state);
      if (!empty($settings_form)) {
        $form['processors']['settings'][$name] = array(
          '#type' => 'details',
          '#title' => $processor->label(),
          '#group' => 'processor_settings',
          '#weight' => $processors_settings[$name]['weight'],
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
    /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
    foreach ($form_state['processors'] as $name => $processor) {
      if (isset($form['#processors'][$name]) && !empty($form['#processors'][$name]['status']) && isset($form_state['values']['processors'][$name]['settings'])) {
        $processor_form_state = $this->getProcessorFormState($name, $form_state);
        $processor->validateConfigurationForm($form['processors']['settings'][$name], $processor_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $values = $form_state['values'];
    // Due to the "#parents" settings, these are all empty arrays.
    unset($values['processors']['settings']);
    unset($values['processors']['status']);
    unset($values['processors']['order']);

    $options = $this->entity->getOptions();

    // Store processor settings.
    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    foreach ($form_state['processors'] as $processor_id => $processor) {
      $processor_form = array();
      if (isset($form['processors']['settings'][$processor_id])) {
        $processor_form = &$form['processors']['settings'][$processor_id];
      }
      $default_settings = array(
        'settings' => array(),
        'processorPluginId' => $processor_id,
      );
      $values['processors'][$processor_id] += $default_settings;

      $processor_form_state = $this->getProcessorFormState($processor_id, $form_state);
      $processor->submitConfigurationForm($processor_form, $processor_form_state);

      $values['processors'][$processor_id]['settings'] = $processor->getConfiguration();
    }

    if (!isset($options['processors']) || $options['processors'] !== $values['processors']) {
      // Save the already sorted arrays to avoid having to sort them at each
      // use.
      uasort($values['processors'], array('Drupal\Component\Utility\SortArray', 'sortByWeightElement'));
      $this->entity->setOption('processors', $values['processors']);

      $this->entity->save();
      $this->entity->reindex();
      drupal_set_message($this->t("The indexing workflow was successfully edited. All content was scheduled for reindexing so the new settings can take effect."));
    }
    else {
      drupal_set_message($this->t('No values were changed.'));
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
   * Gets the filters from the form_state.
   *
   * Returns the portion of the form_state array used in validate and submit
   * that corresponds to the filter being processed.
   *
   * @param string $filter_name
   *   Name of processor/filter
   * @param array $form_state
   *   The form_state array passed into validate and submit methods
   *
   * @return array
   *   The form state of the filter being processed.
   */
  /**
   * Returns a form state reference for a specific processor.
   *
   * For calling a processor's validateConfigurationForm() and
   * submitConfigurationForm(), a specially prepared form state array is
   * necessary, which only contains the processor's settings (if any) in
   * "values". "values" also needs to be a reference, so changes are correctly
   * reflected back to the original form state.
   *
   * @param $processor_id
   *   The ID of the processor for which the form state should be created.
   * @param array $form_state
   *   The form state of the complete form, as a reference.
   *
   * @return array
   *   A sub-form state for the given processor.
   */
  protected function getProcessorFormState($processor_id, array &$form_state) {
    $filter_form_state['values'] = array();
    if (!empty($form_state['values']['processors'][$processor_id]['settings'])) {
      $filter_form_state['values'] = &$form_state['values']['processors'][$processor_id]['settings'];
    }
    return $filter_form_state;
  }

}
