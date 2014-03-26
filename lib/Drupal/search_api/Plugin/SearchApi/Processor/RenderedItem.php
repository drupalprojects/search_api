<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Symfony\Component\Yaml\Yaml;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_rendered_item_processor",
 *   label = @Translation("Rendered Item"),
 *   description = @Translation("Adds an additional field containing the rendered item as it would look when viewed.")
 * )
 *
 */
class RenderedItem extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'view_mode' => NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Builds configuration form
   */
  public function buildConfigurationForm(array $form, array &$form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    //enumerate the view modes from the entity type used as the data source
    //for the index
    $view_modes = array();
    $entity_type = $this->index->getDatasource()->pluginDefinition['entity_type'];
    $entity_label = $this->index->getDatasource()->pluginDefinition['label'];

    foreach (entity_get_view_modes($entity_type) as $key => $mode) {
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
      );
      if ($view_modes) {
        $form['note'] = array(
          '#markup' => '<p>' . t('This index contains entities of type %type and they only have a single view mode. ' .
              'Therefore, no selection needs to be made.', array('%type' => $entity_label)) . '</p>',
        );
      }
      else {
        $form['note'] = array(
          '#markup' => '<p>' . t('This index contains entities of type %type but they have no defined view modes. ' .
              'This might either mean that they are always displayed the same way, or that they cannot be processed by this alteration at all. ' .
              'Please consider this when using this alteration.', array('%type' => $entity_label)) . '</p>',
        );
      }
    }

    return $form;

  }

  public function preprocessIndexItems(array &$items) {
    // Prevent session information from being saved while indexing.
    drupal_save_session(FALSE);

    // Force the current user to anonymous to prevent access bypass in search
    // indexes.
    $original_user = $GLOBALS['user'];
    $GLOBALS['user'] = drupal_anonymous_user();

    $view_mode = empty($this->configuration['mode']) ? 'full' : $this->configuration['mode'];
    foreach ($items as &$item) {
      // Since we can't really know what happens in entity_view() and render(),
      // we use try/catch. This will at least prevent some errors, even though
      // it's no protection against fatal errors and the like.
      try {
        $rendered_item = drupal_render($item->view(), $view_mode);
        if (!$rendered_item) {
          $item->search_api_viewed = NULL;
          continue;
        }
        $item->search_api_viewed = $rendered_item;
      }
      catch (Exception $e) {
        $item->search_api_viewed = NULL;
      }
    }

    // Restore the user.
    $GLOBALS['user'] = $original_user;
    drupal_save_session(TRUE);
  }


}
