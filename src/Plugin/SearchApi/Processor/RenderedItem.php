<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\RenderedItem.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Session\SearchApiUserSession;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiProcessor(
 *   id = "search_api_rendered_item",
 *   label = @Translation("Rendered Item"),
 *   description = @Translation("Adds an additional field containing the rendered item as it would look when viewed.")
 * );
 */
class RenderedItem extends ProcessorPluginBase {

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The typed data manager used by this plugin.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxyInterface $current_user, TypedDataManager $typed_data_manager, TranslationInterface $translation_manager, array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->typedDataManager = $typed_data_manager;
    $this->setTranslationManager($translation_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Session\AccountProxyInterface $current_user */
    $current_user = $container->get('current_user');
    /** @var \Drupal\Core\TypedData\TypedDataManager $typed_data_manager */
    $typed_data_manager = $container->get('typed_data_manager');
    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation_manager */
    $translation_manager = $container->get('string_translation');
    return new static($current_user, $typed_data_manager, $translation_manager, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'view_mode' => array(),
      'roles' => array(DRUPAL_ANONYMOUS_RID),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $view_modes = array();
      foreach ($datasource->getViewModes() as $key => $mode) {
        $view_modes[$key] = $mode['label'];
      }

      if (count($view_modes) > 1) {
        $form['view_mode'][$datasource_id] = array(
          '#type' => 'select',
          '#title' => t('View mode for data source @datasource', array('@datasource' => $datasource->label())),
          '#options' => $view_modes,
        );
        if (isset($this->configuration['view_mode'][$datasource_id])) {
          $form['view_mode'][$datasource_id]['#default_value'] = $this->configuration['view_mode'][$datasource_id];
        }
      }
      elseif ($view_modes) {
        $form['view_mode'][$datasource_id] = array(
          '#type' => 'value',
          '#value' => key($view_modes),
        );
      }
    }

    $form['roles'] = array(
      '#type' => 'select',
      '#title' => $this->t('User roles'),
      '#description' => t('The data will be processed as seen by a user with the selected roles.'),
      '#options' => user_role_names(),
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['roles'],
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if ($datasource) {
      return;
    }
    $definition = array(
      'type' => 'string',
      'label' => $this->t('Rendered HTML output'),
      'description' => $this->t('The complete HTML which would be created when viewing the item.'),
    );
    $properties['search_api_rendered_item'] = new DataDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    // First, extract all the passed item objects.
    foreach ($items as $i => $item) {
      $source = $item->getSource();
      if (isset($source)) {
        $item_objects[$item->getDatasource()->getPluginId()][$i] = $source;
      }
    }

    // Were there any objects passed?
    if (empty($item_objects)) {
      return;
    }

    // Change the current user to our custom AccountInterface implementation
    // so we don't accidentally expose non-public information in this field.
    $original_user = $this->currentUser->getAccount();
    $this->currentUser->setAccount(new SearchApiUserSession($this->configuration['roles']));

    $build = array();
    foreach ($item_objects as $datasource_id => $objects) {
      if (!empty($this->configuration['view_mode'][$datasource_id])) {
        try {
          $build += $this->index->getDatasource($datasource_id)->viewMultipleItems($objects, $this->configuration['view_mode'][$datasource_id]);
        }
        catch (\InvalidArgumentException $e) {
          // Do nothing; we still need to reset the account and $build will be empty
          // anyways.
        }
      }
    }
    // Restore the user.
    $this->currentUser->setAccount($original_user);

    // Now add the rendered items back to the extracted fields.
    foreach ($build as $i => $render) {
      $fields = $items[$i]->extractIndexingFields();
      $fields['search_api_rendered_item']->setValue(array(drupal_render($render)));
      $fields['search_api_rendered_item']->setOriginalType('string');
    }
  }

}
