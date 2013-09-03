<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase.
 */

namespace Drupal\search_api\Plugin\Type\Processor;

use Drupal\Component\Plugin\PluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\query\DefaultQuery;
use Drupal\search_api\Plugin\Type\ProcessorPluginManager;

/**
 * Defines a base processor implementation that most plugins will extend.
 *
 * Simple processors can just override process(), while others might want to
 * override the other process*() methods, and test*() (for restricting
 * processing to something other than all fulltext data).
 */
abstract class ProcessorPluginBase extends PluginBase implements ProcessorInterface {

  /**
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * @var array
   */
  protected $options;

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::__construct().
   *
   * The default implementation saves the parameters into the object's
   * properties.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->index = $this->configuration['index'];
    $this->options = &$this->configuration['options'];;
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * The default implementation always returns TRUE.
   */
  public static function supportsIndex(IndexInterface $index) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->options = $configuration['options'];
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::buildConfigurationForm().
   *
   * The default implementation adds a select list for choosing the fields that
   * should be processed.
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
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

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::validateConfigurationForm().
   *
   * The default implementation brings the fields array into a key-based format.
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    $fields = array_filter($form_state['values']['fields']);
    if ($fields) {
      $fields = array_fill_keys($fields, TRUE);
    }
    $form_state['values']['fields'] = $fields;
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::submitConfigurationForm().
   *
   * The default implementation saves all values into the $options property.
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $state = $form_state;
    form_state_values_clean($state);
    $this->options = $state['values'];
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::propertyInfo().
   *
   * The default implementation does nothing.
   */
  public function propertyInfo() {
    return array();
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::preprocessIndexItems().
   *
   * The default implementation calls processField() for all appropriate fields.
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
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::preprocessSearchQuery().
   *
   * The default implementation calls processKeys() for the keys and
   * processFilters() for the filters.
   */
  public function preprocessSearchQuery(DefaultQuery $query) {
    $keys = &$query->getKeys();
    $this->processKeys($keys);
    $filter = $query->getFilter();
    $filters = &$filter->getFilters();
    $this->processFilters($filters);
  }

  /**
   * Implements \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::postprocessSearchResults().
   *
   * The default implementation does nothing.
   */
  public function postprocessSearchResults(array &$response, DefaultQuery $query) {
    return;
  }

  /**
   * Processes field data.
   *
   * Calls process() either for the whole text, or each token, depending on the
   * type. Also takes care of extracting list values and of fusing returned
   * tokens back into a one-dimensional array.
   *
   * @param $value
   *   The field's value to process.
   * @param $type
   *   The field's type.
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
   * Normalizes tokens into a flat array.
   *
   * @param array $tokens
   *   The tokens to normalize.
   * @param int $score
   *   (optional) The score multiplier to apply, for internal use.
   *
   * @return array
   *   The normalized tokens.
   */
  protected function normalizeTokens(array $tokens, $score = 1) {
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
   * Processes search keys.
   *
   * @param array|string $keys
   *   The search keys as defined by
   *   \Drupal\search_api\Plugin\search_api\QueryInterface::getKeys(), passed by
   *   reference.
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
   * Processes query filters.
   *
   * @param array $filters
   *   The filter array as defined by
   *   \Drupal\search_api\Plugin\search_api\FilterInterface::getFilters(),
   *   passed by reference.
   */
  protected function processFilters(array &$filters) {
    $fields = $this->index->getOption('fields', array());
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
   * Tests whether a certain field should be processed.
   *
   * @param string $name
   *   The field's machine name.
   * @param array $field
   *   The field's information.
   *
   * @return bool
   *   TRUE, iff the field should be processed.
   */
  protected function testField($name, array $field) {
    if (empty($this->options['fields'])) {
      return $this->testType($field['type']);
    }
    return !empty($this->options['fields'][$name]);
  }

  /**
   * Tests whether fields of a certain type should be processed.
   *
   * @param string $type
   *   The type in question.
   *
   * @return bool
   *   TRUE, iff the type should be processed.
   */
  protected function testType($type) {
    return search_api_is_text_type($type, array('text', 'tokens'));
  }

  /**
   * Called for processing a single text element in a field.
   *
   * For fulltext fields, $value can either be left a string, or changed into an
   * array of tokens. A token is an associative array containing:
   * - value: Either the text inside the token, or a nested array of tokens. The
   *   score of nested tokens will be multiplied by their parent's score.
   * - score: The relative importance of the token, as a float, with 1 being
   *   the default.
   *
   * The default implementation just calls process().
   *
   * @param mixed $value
   *   The value to process, passed by reference.
   */
  protected function processFieldValue(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single search keyword.
   *
   * $value can either be left a string, or be changed into a nested keys array,
   * as defined by \Drupal\search_api\Plugin\search_api\QueryInterface.
   *
   * The default implementation just calls process().
   *
   * @param mixed $value
   *   The value to process, passed by reference.
   */
  protected function processKey(&$value) {
    $this->process($value);
  }

  /**
   * Called for processing a single filter value.
   *
   * $value has to remain the same type it was passed as.
   *
   * The default implementation just calls process().
   *
   * @param mixed $value
   *   The value to process, passed by reference.
   */
  protected function processFilterValue(&$value) {
    $this->process($value);
  }

  /**
   * Process a value.
   *
   * This method is eventually called for all text by the standard
   * implementation, and does nothing by default.
   *
   * Since this is called for values of all types and origins, $value has to
   * remain the same type it was passed as.
   *
   * @param mixed $value
   *   The value to process, passed by reference.
   */
  protected function process(&$value) {

  }

}
