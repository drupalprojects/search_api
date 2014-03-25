<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFiltersFormController.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    //$callback_info = search_api_get_alter_callbacks();
    //$processor_info = search_api_get_processors();

    // Fetch all the selected options
    $options = $this->entity->getOptions();

    // Fetch all the processor plugins
    $processor_info = $this->processorPluginManager->getDefinitions();


    $form['#tree'] = TRUE;
    //$form['#attached']['js'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.js';

    // Callbacks

    /*
    $callbacks = empty($options['data_alter_callbacks']) ? array() : $options['data_alter_callbacks'];
    $callback_objects = isset($form_state['callbacks']) ? $form_state['callbacks'] : array();
    foreach ($callback_info as $name => $callback) {
      if (!isset($callbacks[$name])) {
        $callbacks[$name]['status'] = 0;
        $callbacks[$name]['weight'] = $callback['weight'];
      }
      $settings = empty($callbacks[$name]['settings']) ? array() : $callbacks[$name]['settings'];
      if (empty($callback_objects[$name]) && class_exists($callback['class'])) {
        $callback_objects[$name] = new $callback['class']($index, $settings);
      }
      if (!(class_exists($callback['class']) && $callback_objects[$name] instanceof SearchApiAlterCallbackInterface)) {
        watchdog('search_api', t('Data alteration @id specifies illegal callback class @class.', array('@id' => $name, '@class' => $callback['class'])), NULL, WATCHDOG_WARNING);
        unset($callback_info[$name]);
        unset($callbacks[$name]);
        unset($callback_objects[$name]);
        continue;
      }
      if (!$callback_objects[$name]->supportsIndex($index)) {
        unset($callback_info[$name]);
        unset($callbacks[$name]);
        unset($callback_objects[$name]);
        continue;
      }
    }
    $form_state['callbacks'] = $callback_objects;
    $form['#callbacks'] = $callbacks;
    $form['callbacks'] = array(
      '#type' => 'fieldset',
      '#title' => t('Data alterations'),
      '#description' => t('Select the alterations that will be executed on indexed items, and their order.'),
      '#collapsible' => TRUE,
    );

    // Callback status.
    $form['callbacks']['status'] = array(
      '#type' => 'item',
      '#title' => t('Enabled data alterations'),
      '#prefix' => '<div class="search-api-status-wrapper">',
      '#suffix' => '</div>',
    );
    foreach ($callback_info as $name => $callback) {
      $form['callbacks']['status'][$name] = array(
        '#type' => 'checkbox',
        '#title' => $callback['name'],
        '#default_value' => $callbacks[$name]['status'],
        '#parents' => array('callbacks', $name, 'status'),
        '#description' => $callback['description'],
        '#weight' => $callback['weight'],
      );
    }

    // Callback order (tabledrag).
    $form['callbacks']['order'] = array(
      '#type' => 'item',
      '#title' => t('Data alteration processing order'),
      '#theme' => 'search_api_admin_item_order',
      '#table_id' => 'search-api-callbacks-order-table',
    );
    foreach ($callback_info as $name => $callback) {
      $form['callbacks']['order'][$name]['item'] = array(
        '#markup' => $callback['name'],
      );
      $form['callbacks']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#delta' => 50,
        '#default_value' => $callbacks[$name]['weight'],
        '#parents' => array('callbacks', $name, 'weight'),
      );
      $form['callbacks']['order'][$name]['#weight'] = $callbacks[$name]['weight'];
    }

    // Callback settings.
    $form['callbacks']['settings_title'] = array(
      '#type' => 'item',
      '#title' => t('Callback settings'),
    );
    $form['callbacks']['settings'] = array(
      '#type' => 'vertical_tabs',
    );

    foreach ($callback_info as $name => $callback) {
      $settings_form = $callback_objects[$name]->configurationForm();
      if (!empty($settings_form)) {
        $form['callbacks']['settings'][$name] = array(
          '#type' => 'fieldset',
          '#title' => $callback['name'],
          '#parents' => array('callbacks', $name, 'settings'),
          '#weight' => $callback['weight'],
        );
        $form['callbacks']['settings'][$name] += $settings_form;
      }
    }

    */

    // Processors
    $processors = $this->entity->getOption('processors');
    $processor_objects = isset($form_state['processors']) ? $form_state['processors'] : array();
    foreach ($processor_info as $name => $processor) {
      if (!isset($processors[$name])) {
        $processors[$name]['status'] = 0;
      }
      $settings = empty($processors[$name]['settings']) ? array() : $processors[$name]['settings'];

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
      '#type' => 'fieldset',
      '#title' => t('Processors'),
      '#description' => t('Select processors which will pre- and post-process data at index and search time, and their order. ' .
        'Most processors will only influence fulltext fields, but refer to their individual descriptions for details regarding their effect.'),
      '#collapsible' => TRUE,
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
        //'#weight' => $processor['weight'],
      );
    }

    /*

    // Processor order (tabledrag).
    $form['processors']['order'] = array(
      '#type' => 'item',
      '#title' => t('Processor processing order'),
      '#description' => t('Set the order in which preprocessing will be done at index and search time. ' .
        'Postprocessing of search results will be in the exact opposite direction.'),
      '#theme' => 'search_api_admin_item_order',
      '#table_id' => 'search-api-processors-order-table',
    );
    foreach ($processor_info as $name => $processor) {
      $form['processors']['order'][$name]['item'] = array(
        '#markup' => $processor['name'],
      );
      $form['processors']['order'][$name]['weight'] = array(
        '#type' => 'weight',
        '#delta' => 50,
        '#default_value' => $processors[$name]['weight'],
        '#parents' => array('processors', $name, 'weight'),
      );
      $form['processors']['order'][$name]['#weight'] = $processors[$name]['weight'];
    }

    // Processor settings.
    $form['processors']['settings_title'] = array(
      '#type' => 'item',
      '#title' => t('Processor settings'),
    );
    $form['processors']['settings'] = array(
      '#type' => 'vertical_tabs',
    );

    foreach ($processor_info as $name => $processor) {
      $settings_form = $processor_objects[$name]->configurationForm();
      if (!empty($settings_form)) {
        $form['processors']['settings'][$name] = array(
          '#type' => 'fieldset',
          '#title' => $processor['name'],
          '#parents' => array('processors', $name, 'settings'),
          '#weight' => $processor['weight'],
        );
        $form['processors']['settings'][$name] += $settings_form;
      }
    }
    */

    return $form;
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
   * {@inheritdoc}
   *
   * @see book_remove_button_submit()
   */
  public function submit(array $form, array &$form_state) {
    // TODO
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $form, array &$form_state) {
    //$form_state['redirect_route'] = $this->entity->urlInfo('book-remove-form');
  }
}
