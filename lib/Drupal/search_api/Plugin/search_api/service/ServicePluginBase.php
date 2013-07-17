<?php

/**
 * @file
 * Contains Drupal\search_api\Plugin\search_api\service\ServicePluginBase.
 */

namespace Drupal\search_api\Plugin\search_api\service;

/**
 * Abstract class with generic implementation of most service methods.
 *
 * For creating your own service class extending this class, you only need to
 * implement indexItems(), deleteItems() and search() from the
 * ServiceInterface interface.
 */
abstract class ServicePluginBase implements ServiceInterface {

  /**
   * @var Server
   */
  protected $server;

  /**
   * Direct reference to the server's $options property.
   *
   * @var array
   */
  protected $options = array();

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation sets $this->server and $this->options.
   */
  public function __construct(Server $server) {
    $this->server = $server;
    $this->options = &$server->options;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * Returns an empty form by default.
   */
  public function configurationForm(array $form, array &$form_state) {
    return array();
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * Does nothing by default.
   */
  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    return;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation just ensures that additional elements in
   * $options, not present in the form, don't get lost at the update.
   */
  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    if (!empty($this->options)) {
      $values += $this->options;
    }
    $this->options = $values;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation always returns FALSE.
   */
  public function supportsFeature($feature) {
    return FALSE;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation does a crude output as a definition list, with
   * option names taken from the configuration form.
   */
  public function viewSettings() {
    $output = '';
    $form = $form_state = array();
    $option_form = $this->configurationForm($form, $form_state);
    $option_names = array();
    foreach ($option_form as $key => $element) {
      if (isset($element['#title']) && isset($this->options[$key])) {
        $option_names[$key] = $element['#title'];
      }
    }

    foreach ($option_names as $key => $name) {
      $value = $this->options[$key];
      $output .= '<dt>' . check_plain($name) . '</dt>' . "\n";
      $output .= '<dd>' . nl2br(check_plain(print_r($value, TRUE))) . '</dd>' . "\n";
    }

    return $output ? "<dl>\n$output</dl>" : '';
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * Does nothing, by default.
   */
  public function postCreate() {
    return;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation always returns FALSE.
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * By default, deletes all indexes from this server.
   */
  public function preDelete() {
    $indexes = search_api_index_load_multiple(FALSE, array('server' => $this->server->machine_name));
    foreach ($indexes as $index) {
      $this->removeIndex($index);
    }
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * Does nothing, by default.
   */
  public function addIndex(Index $index) {
    return;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation always returns FALSE.
   */
  public function fieldsUpdated(Index $index) {
    return FALSE;
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * By default, removes all items from that index.
   */
  public function removeIndex($index) {
    if (is_object($index) && empty($index->read_only)) {
      $this->deleteItems('all', $index);
    }
  }

  /**
   * Implements ServiceInterface::__construct().
   *
   * The default implementation returns a DefaultQuery object.
   */
  public function query(Index $index, $options = array()) {
    return new DefaultQuery($index, $options);
  }

}
