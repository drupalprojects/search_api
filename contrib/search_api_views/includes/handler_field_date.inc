<?php
// $Id$

/**
 * Views field handler class for treating dates.
 */
class SearchApiViewsHandlerFieldDate extends SearchApiViewsHandlerField {

  /**
   * Specifies the options this handler uses.
   */
  function option_definition() {
    $options = parent::option_definition();
    $options['format']['type'] = array('default' => 'medium');
    $options['format']['custom'] = array('default' => '');
    return $options;
  }

  /**
   * Returns an option form for setting this handler's options.
   */
  function options_form(array &$form, array &$form_state) {
    parent::options_form($form, $form_state);

    $formats = system_get_date_types();
    foreach ($formats as $key => $info) {
      $options[$key] = $info['title'] . ' – ' . format_date(REQUEST_TIME, $key);
    }
    $options['custom'] = t('Custom');
    if ($this->options['format']['custom']) {
      $options['custom'] .= ' – ' . format_date(REQUEST_TIME, 'custom', $this->options['format']['custom']);
    }
    $form['format']['#weight'] = -5;
    $form['format']['type'] = array(
      '#title' => t('Date format'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->options['format']['type'],
    );
    $form['format']['custom'] = array(
      '#title' => t('Custom format'),
      '#type' => 'textfield',
      '#description' => t('Only used if type "@type" is used. See <a href="!url">the PHP documentation</a> for the used format.',
          array('@type' => t('Custom'), '!url' => 'http://php.net/manual/en/function.date.php')),
      '#default_value' => $this->options['format']['custom'],
    );
  }

  /**
   * Helper function for rendering a single value.
   */
  protected function renderValue($value) {
    if ($this->options['format']['type'] == 'custom') {
      return format_date($value, 'custom', $this->options['format']['custom']);
    }
    return format_date($value, $this->options['format']['type']);
  }

}
