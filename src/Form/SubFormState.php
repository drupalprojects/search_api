<?php

/**
 * @file
 * Contains Drupal\search_api\Form\SubFormState.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Represents the form state of a sub-form.
 */
class SubFormState implements FormStateInterface {

  /**
   * The keys which are represented by properties directly in this class.
   *
   * @var bool[]
   */
  protected static $directKeys = array('build_info' => TRUE,
    'values' => TRUE,
    'input' => TRUE,
  );

  /**
   * The keys which should be inherited as-is from the main form state.
   *
   * @var bool[]
   */
  protected static $inheritedKeys = array(
    'build_info' => TRUE,
    'rebuild_info' => TRUE,
    'rebuild' => TRUE,
    'response' => TRUE,
    'redirect' => TRUE,
    'redirect_route' => TRUE,
    'no_redirect' => TRUE,
    'method' => TRUE,
    'cache' => TRUE,
    'no_cache' => TRUE,
    'triggering_element' => TRUE,
  );

  /**
   * The form state of the main form.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $mainFormState;

  /**
   * The keys that lead to the desired sub-form in the main form.
   *
   * @var string[]
   */
  protected $subKeys;

  /**
   * Internal storage for the sub-state, writing into the main form state.
   *
   * @var array
   */
  protected $internalStorage;

  /**
   * The values of the sub-form.
   *
   * @var array
   */
  protected $values;

  /**
   * The input of the sub-form.
   *
   * @var array
   */
  protected $input;

  /**
   * Constructs a SubFormState object.
   *
   * @param \Drupal\Core\Form\FormStateInterface $main_form_state
   *   The state of the main form.
   * @param string[] $sub_keys
   *   The keys that lead to the desired sub-form in the main form.
   */
  public function __construct(FormStateInterface $main_form_state, array $sub_keys) {
    $this->mainFormState = $main_form_state;
    $this->subKeys = $sub_keys;
    $main_form_state->setIfNotExists('sub_states', array());
    $sub_state = &$main_form_state->get('sub_states');
    $this->internalStorage = &$this->applySubKeys($sub_state);
    $this->values = &$this->applySubKeys($main_form_state->get('values'));
    if (!is_array($this->values)) {
      $this->values = array();
    }
    $this->input = &$this->applySubKeys($main_form_state->get('input'));
    if (!is_array($this->input)) {
      $this->input = array();
    }
  }

  /**
   * Applies the sub-form's array keys to the given original array.
   *
   * @param array $original
   *   The original array, belonging to the main form.
   *
   * @return array
   *   The corresponding array for the sub form, as a reference.
   */
  protected function &applySubKeys(array &$original) {
    $return = &$original;
    foreach ($this->subKeys as $key) {
      $return = &$return[$key];
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function &getCompleteForm() {
    return $this->applySubKeys($this->mainFormState->getCompleteForm());
  }

  /**
   * {@inheritdoc}
   */
  public function setCompleteForm(array &$complete_form) {
    $sub_form = &$this->applySubKeys($this->mainFormState->getCompleteForm());
    $sub_form = $complete_form;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function loadInclude($module, $type, $name = NULL) {
    return $this->mainFormState->loadInclude($module, $type, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableArray() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function setFormState(array $form_state_additions) {
    foreach ($form_state_additions as $key => $value) {
      $this->set($key, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($property, $value) {
    if (!$this->has($property)) {
      $this->set($property, $value);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setResponse(Response $response) {
    $this->mainFormState->setResponse($response);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirect($route_name, array $route_parameters = array(), array $options = array()) {
    $this->mainFormState->setRedirect($route_name, $route_parameters, $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRedirectUrl(Url $url) {
    $this->mainFormState->setRedirectUrl($url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirect() {
    return $this->mainFormState->getRedirect();
  }

  /**
   * {@inheritdoc}
   */
  public function &get($property) {
    if (isset(self::$directKeys[$property])) {
      return $this->$property;
    }
    if (isset(self::$inheritedKeys[$property])) {
      return $this->mainFormState->get($property);
    }
    if (array_key_exists($property, $this->internalStorage)) {
      return $this->internalStorage[$property];
    }
    $null = NULL;
    return $null;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property, $value) {
    if (isset(self::$directKeys[$property])) {
      $this->$property = $value;
    }
    elseif (isset(self::$inheritedKeys[$property])) {
      $this->mainFormState->set($property, $value);
    }
    else {
      $this->internalStorage[$property] = $value;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function has($property) {
    return isset(self::$directKeys[$property])
        || isset(self::$inheritedKeys[$property])
        || array_key_exists($property, $this->internalStorage);
  }

  /**
   * {@inheritdoc}
   */
  public function addBuildInfo($property, $value) {
    $this->mainFormState->addBuildInfo($property, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getUserInput() {
    $user_input = &$this->mainFormState->getUserInput();
    return $this->applySubKeys($user_input);
  }

  /**
   * {@inheritdoc}
   */
  public function setUserInput(array $user_input) {
    $old = &$this->getUserInput();
    $old = $user_input;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getValues() {
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function &getValue($key, $default = NULL) {
    if ($this->hasValue($key)) {
      return $this->values[$key];
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($key, $value) {
    $this->values[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetValue($key) {
    unset($this->values[$key]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValue($key) {
    if (isset($this->values[$key])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isValueEmpty($key) {
    if (empty($this->values[$key])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setValueForElement($element, $value) {
    $this->mainFormState->setValueForElement($element, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function hasAnyErrors() {
    return FormState::hasAnyErrors();
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorByName($name, $message = '') {
    $this->mainFormState->setErrorByName(implode('][', $this->subKeys) . '][' . $name, $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setError(&$element, $message = '') {
    $this->mainFormState->setError($element, $message);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearErrors() {
    $this->mainFormState->clearErrors();
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors() {
    return $this->mainFormState->getErrors();
  }

  /**
   * {@inheritdoc}
   */
  public function getError($element) {
    return $this->mainFormState->getError($element);
  }

  /**
   * {@inheritdoc}
   */
  public function setRebuild($rebuild = TRUE) {
    $this->mainFormState->setRebuild($rebuild);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareCallback($callback) {
    return $this->mainFormState->prepareCallback($callback);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormObject() {
    return $this->mainFormState->getFormObject();
  }

}
