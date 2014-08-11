<?php

/**
 * @file
 * Contains SearchApiViewsHandlerFilterEntity.
 */

namespace Drupal\search_api\Plugin\views\filter;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Form\FormStateInterface;

/**
 * Views filter handler class for entities.
 *
 * Should be extended for specific entity types, such as
 * SearchApiViewsHandlerFilterUser and SearchApiViewsHandlerFilterTaxonomyTerm.
 *
 * Based on \Drupal\taxonomy\Plugin\views\argument\IndexTid.
 */
abstract class SearchApiFilterEntityBase extends SearchApiFilter {

  /**
   * If exposed form input was successfully validated, the entered entity IDs.
   *
   * @var array
   */
  protected $validated_exposed_input;

  /**
   * Validates entered entity labels and converts them to entity IDs.
   *
   * Since this can come from either the form or the exposed filter, this is
   * abstracted out a bit so it can handle the multiple input sources.
   *
   * @param array $form
   *   The form or form element for which any errors should be set.
   * @param array $values
   *   The entered user names to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The entity IDs corresponding to all entities that could be found.
   */
  abstract protected function validateEntityStrings(array &$form, array $values, FormStateInterface $form_state);

  /**
   * Transforms an array of entity IDs into a comma-separated list of labels.
   *
   * @param array $ids
   *   The entity IDs to transform.
   *
   * @return string
   *   A string containing the labels corresponding to the IDs, separated by
   *   commas.
   */
  abstract protected function idsToStrings(array $ids);

  /**
   * {@inheritdoc}
   */
  public function operatorOptions() {
    $operators = array(
      '=' => $this->isMultiValued() ? $this->t('Is one of') : $this->t('Is'),
      'all of' => $this->t('Is all of'),
      '<>' => $this->isMultiValued() ? $this->t('Is not one of') : $this->t('Is not'),
      'empty' => $this->t('Is empty'),
      'not empty' => $this->t('Is not empty'),
    );
    if (!$this->isMultiValued()) {
      unset($operators['all of']);
    }
    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['expose']['multiple']['default'] = TRUE;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (!is_array($this->value)) {
      $this->value = $this->value ? array($this->value) : array();
    }

    // Set the correct default value in case the admin-set value is used (and a
    // value is present). The value is used if the form is either not exposed,
    // or the exposed form wasn't submitted yet. (There doesn't seem to be an
    // easier way to check for that.)
    if ($this->value && (!$form_state->get('input') || !empty($form_state->get('input')['live_preview']))) {
      $form['value']['#default_value'] = $this->idsToStrings($this->value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function valueValidate($form, FormStateInterface $form_state) {
    if (!empty($form['value'])) {
      $form_values = $form_state->getValues();
      $value = $form_values['options']['value'];
      $values = $this->isMultiValued($form_values['options']) ? Tags::explode($value) : array($value);
      $ids = $this->validateEntityStrings($form['value'], $values, $form_state);

      if ($ids) {
        $value = $ids;
        $form_state->setValue('value', $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $rc = parent::acceptExposedInput($input);

    if ($rc) {
      // If we have previously validated input, override.
      if ($this->validated_exposed_input) {
        $this->value = $this->validated_exposed_input;
      }
    }

    return $rc;
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed']) || empty($this->options['expose']['identifier'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $input = $form_state->getValues()[$identifier];

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->operator = $this->options['group_info']['group_items'][$input]['operator'];
      $input = $this->options['group_info']['group_items'][$input]['value'];
    }

    $values = $this->isMultiValued() ? Tags::explode($input) : array($input);

    if (!$this->options['is_grouped'] || ($this->options['is_grouped'] && ($input != 'All'))) {
      $this->validated_exposed_input = $this->validateEntityStrings($form[$identifier], $values, $form_state);
    }
    else {
      $this->validated_exposed_input = FALSE;
    }
  }

  /**
   * Determines whether multiple user names can be entered into this filter.
   *
   * This is either the case if the form isn't exposed, or if the " Allow
   * multiple selections" option is enabled.
   *
   * @param array $options
   *   (optional) The options array to use. If not supplied, the options set on
   *   this filter will be used.
   *
   * @return bool
   *   TRUE if multiple values can be entered for this filter, FALSE otherwise.
   */
  protected function isMultiValued(array $options = array()) {
    $options = $options ? $options : $this->options;
    return empty($options['exposed']) || !empty($options['expose']['multiple']);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!is_array($this->value)) {
      $this->value = $this->value ? array($this->value) : array();
    }
    $value = $this->value;
    $this->value = empty($value) ? '' : $this->idsToStrings($value);
    $ret = parent::adminSummary();
    $this->value = $value;
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->operator === 'empty') {
      $this->query->condition($this->realField, NULL, '=', $this->options['group']);
    }
    elseif ($this->operator === 'not empty') {
      $this->query->condition($this->realField, NULL, '<>', $this->options['group']);
    }
    elseif (is_array($this->value)) {
      $all_of = $this->operator === 'all of';
      $operator = $all_of ? '=' : $this->operator;
      if (count($this->value) == 1) {
        $this->query->condition($this->realField, reset($this->value), $operator, $this->options['group']);
      }
      else {
        $filter = $this->query->createFilter($operator === '<>' || $all_of ? 'AND' : 'OR');
        foreach ($this->value as $value) {
          $filter->condition($this->realField, $value, $operator);
        }
        $this->query->filter($filter, $this->options['group']);
      }
    }
  }

}
