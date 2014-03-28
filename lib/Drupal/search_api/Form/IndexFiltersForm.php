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
    // Fetch our active Processors for this index
    $processors_by_weight = $this->entity->getProcessors(TRUE, 'weight');
    // Fetch all processors
    $processors_by_name = isset($form_state['processors']) ? $form_state['processors'] : $this->entity->getProcessors(TRUE, 'name');
    // Fetch the settings for all configured processors on this index
    $processors_settings = $this->entity->getOption('processors');

    // Make sure that we have weights and status for all processors, even new ones
    foreach ($processors_by_name as $name => $processor) {
      // Set some sensible defaults for weight and status
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

    foreach ($processors_by_name as $name => $processor) {
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $form['processors']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $processor->label(),
        '#default_value' => $processors_settings[$name]['status'],
        '#parents' => array('processors', $name, 'status'),
        '#description' => $processor->getPluginDefinition()['description'],
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

    foreach ($processors_by_weight as $name => $processor) {
      /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
      $form['processors']['order'][$name]['#attributes']['class'][] = 'draggable';
      $form['processors']['order'][$name]['label'] = array(
        '#markup' => String::checkPlain($processor->label()),
      );

      // TableDrag: Weight column element.
      $form['processors']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $processor->label())),
        '#title_display' => 'invisible',
        '#default_value' => $processors_settings[$name]['weight'],
        '#parents' => array('processors', $name, 'weight'),
        '#attributes' => array('class' => array('search-api-processor-weight')),
      );
    }

    // Processor settings.
    $form['processor_settings'] = array(
      '#title' => t('Processor settings'),
      '#type' => 'vertical_tabs',
    );

    foreach ($processors_by_weight as $name => $processor) {
      /** @var $processor_plugin \Drupal\search_api\Processor\ProcessorInterface */
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
    foreach ($form_state['processors'] as $name => $processor) {
      if (isset($form['#processors'][$name]) && !empty($form['#processors'][$name]['status']) && isset($form_state['values']['processors'][$name]['settings'])) {
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
      $values['processors'][$name] += array('settings' => array());

      if (!empty($form['#processors'][$name]['status'])) {
        // We have to create our own form_state for the plugin form in order to
        // get it to save correctly. This ensures we can use submitConfigurationForm
        // in the correct manner, as described in
        $processor_form_state = $this->getFilterFormState($name, $form_state);
        $processor->submitConfigurationForm($processor_form, $processor_form_state);

        /** @var $processor \Drupal\search_api\Processor\ProcessorInterface */
        $values['processors'][$name]['settings'] = $processor->getConfiguration();
      }
    }

    if (!isset($options['processors']) || $options['processors'] !== $values['processors']) {
      // Save the already sorted arrays to avoid having to sort them at each use.
      uasort($values['processors'], array($this, 'elementCompare'));
      $this->entity->setOption('processors', $values['processors']);

      // Reset the index's internal property cache to correctly incorporate the
      // new data alterations.
      $this->entity->resetCaches();

      $this->entity->save();
      $this->entity->reindex();
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
