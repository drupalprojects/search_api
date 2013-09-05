<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\Type\Service\ServicePluginBase.
 */

namespace Drupal\search_api\Plugin\Type\Service;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\query\DefaultQuery;
use Drupal\search_api\ServerInterface;

/**
 * Abstract class with generic implementation of most service methods.
 *
 * For creating your own service class extending this class, you only need to
 * implement indexItems(), deleteItems() and search() from the
 * ServiceInterface interface.
 */
abstract class ServicePluginBase implements ServiceInterface {

  /**
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Direct reference to the server's $options property.
   *
   * @var array
   */
  protected $options = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(ServerInterface $server) {
    $this->server = $server;
    $this->options = &$server->options;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationForm(array $form, array &$form_state) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function configurationFormValidate(array $form, array &$values, array &$form_state) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationFormSubmit(array $form, array &$values, array &$form_state) {
    if (!empty($this->options)) {
      $values += $this->options;
    }
    $this->options = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function postCreate() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    $indexes = search_api_index_load_multiple_by_properties(array('server' => $this->server->id()));
    foreach ($indexes as $index) {
      $this->removeIndex($index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldsUpdated(IndexInterface $index) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex(IndexInterface $index) {
    if (is_object($index) && empty($index->read_only)) {
      $this->deleteItems('all', $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(IndexInterface $index, $options = array()) {
    return new DefaultQuery($index, $options);
  }

}
