<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\LanguageControl.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Adds enahnced language handling to the indexing workflow.
 *
 * Allows users to specify which field to use for the field's language, and to
 * filter out items based on their language.
 *
 * @SearchApiProcessor(
 *   id = "search_api_language_control",
 *   name = @Translation("Language control"),
 *   description = @Translation("Lets you determine the language of items in the index."),
 *   weight = -20
 * )
 */
class LanguageControl extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->options += array(
      'lang_field' => '',
      'languages' => array(),
    );
  }

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * Only returns TRUE if the system is multilingual.
   */
  public static function supportsIndex(IndexInterface $index) {
    return drupal_multilingual();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = array();

    $wrapper = $this->index->entityWrapper();
    $fields[''] = t('- Use default -');
    foreach ($wrapper as $key => $property) {
      if ($key == 'search_api_language') {
        continue;
      }
      $type = $property->type();
      // Only single-valued string properties make sense here. Also, nested
      // properties probably don't make sense.
      if ($type == 'text' || $type == 'token') {
        $info = $property->info();
        $fields[$key] = $info['label'];
      }
    }

    if (count($fields) > 1) {
      $form['lang_field'] = array(
        '#type' => 'select',
        '#title' => t('Language field'),
        '#description' => t("Select the field which should be used to determine an item's language."),
        '#options' => $fields,
        '#default_value' => $this->options['lang_field'],
      );
    }

    $languages[LANGUAGE_NONE] = t('Language neutral');
    $list = language_list('enabled') + array(array(), array());
    foreach (array($list[1], $list[0]) as $list) {
      foreach ($list as $lang) {
        $name = t($lang->name);
        $native = $lang->native;
        $languages[$lang->language] = ($name == $native) ? $name : "$name ($native)";
        if (!$lang->enabled) {
          $languages[$lang->language] .= ' [' . t('disabled') . ']';
        }
      }
    }
    $form['languages'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Indexed languages'),
      '#description' => t('Index only items in the selected languages. ' .
          'When no language is selected, there will be no language-related restrictions.'),
      '#options' => $languages,
      '#default_value' => $this->options['languages'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $form_state['values']['languages'] = array_filter($form_state['values']['languages']);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as $i => &$item) {
      // Set item language, if a custom field was selected.
      if ($field = $this->options['lang_field']) {
        $wrapper = $this->index->entityWrapper($item);
        if (isset($wrapper->$field)) {
          try {
            $item->search_api_language = $wrapper->$field->value();
          }
          catch (EntityMetadataWrapperException $e) {
            // Something went wrong while accessing the language field. Probably
            // doesn't really matter.
          }
        }
      }
      // Filter out items according to language, if any were selected.
      if ($languages = $this->options['languages']) {
        if (empty($languages[$item->search_api_language])) {
          unset($items[$i]);
        }
      }
    }
  }

}
