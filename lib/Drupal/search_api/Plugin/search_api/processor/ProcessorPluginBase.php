<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\processor\ProcessorPluginBase.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

/**
 * Abstract processor implementation that provides an easy framework for only
 * processing specific fields.
 *
 * Simple processors can just override process(), while others might want to
 * override the other process*() methods, and test*() (for restricting
 * processing to something other than all fulltext data).
 */
abstract class ProcessorPluginBase implements ProcessorInterface {

  /**
   * @var Index
   */
  protected $index;

  /**
   * @var array
   */
  protected $options;

  /**
   * Constructor, saving its arguments into properties.
   */
  public function __construct(Index $index, array $options = array()) {
    $this->index   = $index;
    $this->options = $options;
  }

  public function supportsIndex(Index $index) {
    return TRUE;
  }

  public function configurationForm() {
    $form['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';

    $fields = $this->index->getFields();
    $field_options = array();
    $default_fields = array();
    if (isset($this->options['fields'])) {
      $default_fields = drupal_map_assoc(array_keys($this->options['fields']));
    }
    foreach ($fields as $name => $field) {
      $field_options[$name] = $field['name'];
      if (!empty($default_fields[$name]) || (!isset($this->options['fields']) && $this->testField($name, $field))) {
        $default_fields[$name] = $name;
      }
    }

    $form['fields'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fields to run on'),
      '#options' => $field_options,
      '#default_value' => $default_fields,
      '#attributes' => array('class' => array('search-api-checkboxes-list')),
    );

    return $form;
  }

  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    $fields = array_filter($values['fields']);
    if ($fields) {
      $fields = array_fill_keys($fields, TRUE);
    }
    $values['fields'] = $fields;
  }

  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    $this->options = $values;
    return $values;
  }

  /**
   * Calls processField() for all appropriate fields.
   */
  public function preprocessIndexItems(array &$items) {
    foreach ($items as &$item) {
      foreach ($item as $name => &$field) {
        if ($this->testField($name, $field)) {
          $this->processField($field['value'], $field['type']);
        }
      }
    }
  }

  /**
   * Calls processKeys() for the keys and processFilters() for the filters.
   */
  public function preprocessSearchQuery(DefaultQuery $query) {
    $keys = &$query->getKeys();
    $this->processKeys($keys);
    $filter = $query->getFilter();
    $filters = &$filter->getFilters();
    $this->processFilters($filters);
  }

  /**
   * Does nothing.
   */
  public function postprocessSearchResults(array &$response, DefaultQuery $query) {
    return;
  }

  /**
   * Method for preprocessing field data.
   *
   * Calls process() either for the whole text, or each token, depending on the
   * type. Also takes care of extracting list values and of fusing returned
   * tokens back into a one-dimensional array.
   */
  protected function processField(&$value, &$type) {
    if (!isset($value) || $value === '') {
      return;
    }
    if (substr($type, 0, 5) == 'list<') {
      $inner_type = $t = $t1 = substr($type, 5, -1);
      foreach ($value as &$v) {
        $t1 = $inner_type;
        $this->processField($v, $t1);
        // If one value got tokenized, all others have to follow.
        if ($t1 != $inner_type) {
          $t = $t1;
        }
      }
      if ($t == 'tokens') {
        foreach ($value as $i => &$v) {
          if (!$v) {
            unset($value[$i]);
            continue;
          }
          if (!is_array($v)) {
            $v = array(array('value' => $v, 'score' => 1));
          }
        }
      }
      $type = "list<$t>";
      return;
    }
    if ($type == 'tokens') {
      foreach ($value as &$token) {
        $this->processFieldValue($token['value']);
      }
    }
    else {
      $this->processFieldValue($value);
    }
    if (is_array($value)) {
      // Don't tokenize non-fulltext content!
      if (in_array($type, array('text', 'tokens'))) {
        $type = 'tokens';
        $value = $this->normalizeTokens($value);
      }
      else {
        $value = $this->implodeTokens($value);
      }
    }
  }

  /**
   * Internal helper function for normalizing tokens.
   */
  protected function normalizeTokens($tokens, $score = 1) {
    $ret = array();
    foreach ($tokens as $token) {
      if (empty($token['value']) && !is_numeric($token['value'])) {
        // Filter out empty tokens.
        continue;
      }
      if (!isset($token['score'])) {
        $token['score'] = $score;
      }
      else {
        $token['score'] *= $score;
      }
      if (is_array($token['value'])) {
        foreach ($this->normalizeTokens($token['value'], $token['score']) as $t) {
          $ret[] = $t;
        }
      }
      else {
        $ret[] = $token;
      }
    }
    return $ret;
  }

  /**
   * Internal helper function for imploding tokens into a single string.
   *
   * @param array $tokens
   *   The tokens array to implode.
   *
   * @return string
   *   The text data from the tokens concatenated into a single string.
   */
  protected function implodeTokens(array $tokens) {
    $ret = array();
    foreach ($tokens as $token) {
      if (empty($token['value']) && !is_numeric($token['value'])) {
        // Filter out empty tokens.
        continue;
      }
      if (is_array($token['value'])) {
        $ret[] = $this->implodeTokens($token['value']);
      }
      else {
        $ret[] = $token['value'];
      }
    }
    return implode(' ', $ret);
  }

  /**
   * Method for preprocessing search keys.
   */
  protected function processKeys(&$keys) {
    if (is_array($keys)) {
      foreach ($keys as $key => &$v) {
        if (element_child($key)) {
          $this->processKeys($v);
          if (!$v && !is_numeric($v)) {
            unset($keys[$key]);
          }
        }
      }
    }
    else {
      $this->processKey($keys);
    }
  }

  /**
   * Method for preprocessing query filters.
   */
  protected function processFilters(array &$filters) {
    $fields = $this->index->options['fields'];
    foreach ($filters as $key => &$f) {
      if (is_array($f)) {
        if (isset($fields[$f[0]]) && $this->testField($f[0], $fields[$f[0]])) {
          // We want to allow processors also to easily remove complete filters.
          // However, we can't use empty() or the like, as that would sort out
          // filters for 0 or NULL. So we specifically check only for the empty
          // string, and we also make sure the filter value was actually changed
          // by storing whether it was empty before.
          $empty_string = $f[1] === '';
          $this->processFilterValue($f[1]);

          if ($f[1] === '' && !$empty_string) {
            unset($filters[$key]);
          }
        }
      }
      else {
        $child_filters = &$f->getFilters();
        $this->processFilters($child_filters);
      }
    }
  }

  /**
   * @param $name
   *   The field's machine name.
   * @param array $field
   *   The field's information.
   *
   * @return
   *   TRUE, iff the field should be processed.
   */
  protected function testField($name, array $field) {
    if (empty($this->options['fields'])) {
      return $this->testType($field['type']);
    }
    return !empty($this->options['fields'][$name]);
  }

  /**
   * @return
   *   TRUE, iff the type should be processed.
   */
  protected function testType($type) {
    return search_api_is_text_type($type, array('text', 'tokens'));
  }

  /**
   * Called for processing a single text element in a field. The default
   * implementation just calls process().
   *
   * $value can either be left a string, or changed into an array of tokens. A
   * token is an associative array containing:
   * - value: Either the text inside the token, or a nested array of tokens. The
   *   score of nested tokens will be multiplied by their parent's score.
   * - score: The relative importance of the token, as a float, with 1 being
   *   the default.
   */
  protected function processFieldValue(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single search keyword. The default implementation
   * just calls process().
   *
   * $value can either be left a string, or be changed into a nested keys array,
   * as defined by QueryInterface.
   */
  protected function processKey(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single filter value. The default implementation
   * just calls process().
   *
   * $value has to remain a string.
   */
  protected function processFilterValue(&$value) {
    $this->process($value);
  }

  /**
   * Function that is ultimately called for all text by the standard
   * implementation, and does nothing by default.
   *
   * @param $value
   *   The value to preprocess as a string. Can be manipulated directly, nothing
   *   has to be returned. Since this can be called for all value types, $value
   *   has to remain a string.
   */
  protected function process(&$value) {

  }

}
