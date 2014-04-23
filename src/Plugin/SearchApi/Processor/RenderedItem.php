<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\RenderedItem.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManager;
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
      'view_mode' => NULL,
      'roles' => array(DRUPAL_ANONYMOUS_RID),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $view_modes = array();
    foreach ($this->index->getDatasource()->getViewModes() as $key => $mode) {
      $view_modes[$key] = $mode['label'];
    }

    if (count($view_modes) > 1) {
      $form['view_mode'] = array(
        '#type' => 'select',
        '#title' => t('View mode'),
        '#options' => $view_modes,
        '#default_value' => $this->configuration['view_mode'],
      );
    }
    else {
      $form['view_mode'] = array(
        '#type' => 'value',
        '#value' => $this->configuration['view_mode'],
        '#default_value' => key($view_modes),
      );
      $type_label = $this->index->getDatasource()->label();
      if ($view_modes) {
        $form['note'] = array(
          '#markup' => '<p>' . $this->t('This index contains entities of type %type and they only have a single view mode. Therefore, no selection needs to be made.', array('%type' => $type_label)) . '</p>',
        );
      }
      else {
        $form['note'] = array(
          '#markup' => '<p>' . $this->t('This index contains items of type %type but they have no defined view modes. This might either mean that they are always displayed the same way, or that they cannot be processed by this alteration at all. Please consider this when using this alteration.', array('%type' => $type_label)) . '</p>',
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
  public function alterPropertyDefinitions(array &$properties) {
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
    // First, check if the processir is even enabled.
    $processors = $this->index->getOption('processors');

    if (empty($processors['search_api_rendered_item'])) {
      return;
    }

    // Then, extract all the passed item objects.
    foreach ($items as $i => $item) {
      if (isset($item['#item'])) {
        $item_objects[$i] = $item['#item'];
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

    // Since we can't really know what happens in entity_view() and render(),
    // we use try/catch. This will at least prevent some errors, even though
    // it's no protection against fatal errors and the like.
    $build = array();
    try {
      $build = $this->index->getDatasource()->viewMultipleItems($item_objects, $this->configuration['view_mode']);
    }
    catch (\Exception $e) {
      // Do nothing; we still need to reset the account and $build will be empty
      // anyways.
    }
    // Restore the user.
    $this->currentUser->setAccount($original_user);

    // Now add the rendered items back to the extracted fields.
    foreach ($build as $i => $render) {
      $items[$i]['search_api_rendered_item']['value'][] = drupal_render($render);
      $items[$i]['search_api_rendered_item']['original_type'] = 'string';
    }
  }

}
