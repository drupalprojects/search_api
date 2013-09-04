<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\AddViewedEntity.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Adds the rendered entity's HTML to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "search_api_add_viewed_entity",
 *   name = @Translation("Complete entity view"),
 *   description = @Translation("Adds an additional field containing the whole HTML content of the entity when viewed."),
 *   weight = -10
 * )
 */
class AddViewedEntity extends ProcessorPluginBase {

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * Only support entities.
   */
  public static function supportsIndex(IndexInterface $index) {
    return (bool) $index->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $view_modes = array();
    if ($entity_type = $this->index->getEntityType()) {
      $info = entity_get_info($entity_type);
      foreach ($info['view modes'] as $key => $mode) {
        $view_modes[$key] = $mode['label'];
      }
    }
    $this->options += array('mode' => reset($view_modes));
    if (count($view_modes) > 1) {
      $form['mode'] = array(
        '#type' => 'select',
        '#title' => t('View mode'),
        '#options' => $view_modes,
        '#default_value' => $this->options['mode'],
      );
    }
    else {
      $form['mode'] = array(
        '#type' => 'value',
        '#value' => $this->options['mode'],
      );
      if ($view_modes) {
        $form['note'] = array(
          '#markup' => '<p>' . t('Entities of type %type have only a single view mode. ' .
              'Therefore, no selection needs to be made.', array('%type' => $info['label'])) . '</p>',
        );
      }
      else {
        $form['note'] = array(
          '#markup' => '<p>' . t('Entities of type %type have no defined view modes. ' .
              'This might either mean that they are always displayed the same way, or that they cannot be processed by this alteration at all. ' .
              'Please consider this when using this alteration.', array('%type' => $info['label'])) . '</p>',
        );
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return array(
      'search_api_viewed' => array(
        'label' => t('Entity HTML output'),
        'description' => t('The whole HTML content of the entity when viewed.'),
        'type' => 'text',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    // Prevent session information from being saved while indexing.
    drupal_save_session(FALSE);

    // Force the current user to anonymous to prevent access bypass in search
    // indexes.
    $original_user = $GLOBALS['user'];
    $GLOBALS['user'] = drupal_anonymous_user();

    $type = $this->index->getEntityType();
    $mode = empty($this->options['mode']) ? 'full' : $this->options['mode'];
    foreach ($items as $id => &$item) {
      // Since we can't really know what happens in entity_view() and render(),
      // we use try/catch. This will at least prevent some errors, even though
      // it's no protection against fatal errors and the like.
      try {
        $render = entity_view($type, array(entity_id($type, $item) => $item), $mode);
        $text = render($render);
        if (!$text) {
          $item->search_api_viewed = NULL;
          continue;
        }
        $item->search_api_viewed = $text;
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
