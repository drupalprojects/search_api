<?php
// $Id$

/**
 * Processor for making searches case-insensitive.
 */
class SearchApiIgnoreCase extends SearchApiAbstractProcessor {

  public function configurationForm() {
    $form = array(
      'strings' => array(
        '#type' => 'checkbox',
        '#title' => t('Process strings'),
        '#description' => t('Determines whether this processor will process string fields, too.'),
      ),
    );

    if (!empty($this->options)) {
      $form['strings']['#default_value']   = $this->options['strings'];
    }

    return $form;
  }

  public function testType($type) {
    $allowed = array('text', 'tokens');
    if (!empty($this->options['strings'])) {
      $allowed[] = 'string';
    }
    return search_api_is_text_type($type, $allowed);;
  }

  protected function process(&$value) {
    $value = drupal_strtolower($value);
  }

}
