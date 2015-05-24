<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\RenderedItem.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\UserSession;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Property\BasicProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiProcessor(
 *   id = "rendered_item",
 *   label = @Translation("Rendered item"),
 *   description = @Translation("Adds an additional field containing the rendered item as it would look when viewed."),
 *   stages = {
 *     "preprocess_index" = 0
 *   }
 * )
 */
class RenderedItem extends ProcessorPluginBase {

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|null
   */
  protected $currentUser;

  /**
   * The renderer to use.
   *
   * @var \Drupal\Core\Render\RendererInterface|null
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Session\AccountProxyInterface $current_user */
    $current_user = $container->get('current_user');
    $plugin->setCurrentUser($current_user);

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $container->get('renderer');
    $plugin->setRenderer($renderer);

    return $plugin;
  }

  /**
   * Retrieves the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  public function getCurrentUser() {
    return $this->currentUser ?: \Drupal::currentUser();
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @return $this
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
    return $this;
  }

  /**
   * Retrieves the renderer.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer.
   */
  public function getRenderer() {
    return $this->renderer ?: \Drupal::service('renderer');
  }

  /**
   * Sets the renderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The new renderer.
   *
   * @return $this
   */
  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  // @todo Add a supportsIndex() implementation that checks whether there is
  //   actually any datasource present which supports viewing.

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'roles' => array(AccountInterface::ANONYMOUS_ROLE),
      'view_mode' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $roles = user_role_names();
    $form['roles'] = array(
      '#type' => 'select',
      '#title' => $this->t('User roles'),
      '#description' => $this->t('Your item will be rendered as seen by a user with the selected roles. We recommend to just use "@anonymous" here to prevent data leaking out to unauthorized roles.', array('@anonymous' => $roles[AccountInterface::ANONYMOUS_ROLE])),
      '#options' => $roles,
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['roles'],
      '#required' => TRUE,
    );

    $form['view_mode'] = array(
      '#type' => 'item',
      '#description' => $this->t('You can choose the view modes to use for rendering the items of different datasources and bundles. We recommend using a dedicated view mode (e.g., the "Search index" view mode available by default for content) to make sure that only relevant data (especially no field labels) will be included in the index.'),
    );

    $options_present = FALSE;
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $bundles = $datasource->getBundles();
      foreach ($bundles as $bundle_id => $bundle_label) {
        $view_modes = $datasource->getViewModes($bundle_id);
        if (count($view_modes) > 1) {
          $form['view_mode'][$datasource_id][$bundle_id] = array(
            '#type' => 'select',
            '#title' => $this->t('View mode for %datasource » %bundle', array('%datasource' => $datasource->label(), '%bundle' => $bundle_label)),
            '#options' => $view_modes,
          );
          if (isset($this->configuration['view_mode'][$datasource_id][$bundle_id])) {
            $form['view_mode'][$datasource_id][$bundle_id]['#default_value'] = $this->configuration['view_mode'][$datasource_id][$bundle_id];
          }
          $options_present = TRUE;
        }
        elseif ($view_modes) {
          $form['view_mode'][$datasource_id][$bundle_id] = array(
            '#type' => 'value',
            '#value' => key($view_modes),
          );
        }
      }
    }
    // If there are no datasources/bundles with more than one view mode, don't
    // display the description either.
    if (!$options_present) {
      unset($form['view_mode']['#type']);
      unset($form['view_mode']['#description']);
    }

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
      'description' => $this->t('The complete HTML which would be displayed when viewing the item'),
    );
    $properties['rendered_item'] = BasicProperty::createFromDefinition($definition)->setLocked();
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    // Change the current user to our dummy implementation to ensure we are
    // using the configured roles.
    $original_user = $this->currentUser->getAccount();
    // @todo Why not just use \Drupal\Core\Session\UserSession directly here?
    $this->currentUser->setAccount(new UserSession(array('roles' => $this->configuration['roles'])));

    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      if (empty($this->configuration['view_mode'][$item->getDatasourceId()])) {
        continue;
      }
      if (!($field = $item->getField('rendered_item'))) {
        continue;
      }
      $build = $item->getDatasource()->viewItem($item->getOriginalObject(), $this->configuration['view_mode'][$item->getDatasourceId()]);
      $field->addValue($this->getRenderer()->render($build));
    }

    // Restore the original user.
    $this->currentUser->setAccount($original_user);
  }

}
