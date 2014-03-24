<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\ServerListController.
 */

namespace Drupal\search_api\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Component\Utility\String;

/**
 * Defines a list controller for the Server entity.
 */
class ServerListController extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    // Initialize the entities variable to default array structure.
    $entities = array(
      'enabled' => array(),
      'disabled' => array(),
    );
    // Iterate through the available entities.
    foreach (parent::load() as $entity) {
      // Get the status key based upon the entity status.
      $status_key = $entity->status() ? 'enabled' : 'disabled';
      // Add the entity to the list.
      $entities[$status_key][] = $entity;
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return array(
      'status' => $this->t('Status'),
      'type' => $this->t('Type'),
      'title' => $this->t('Name'),
      'service' => array(
        'data' => $this->t('Service'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'operations' => $this->t('Operations'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(\Drupal\Core\Entity\EntityInterface $entity) {
    // Check if the server contains a valid service.
    if ($entity->hasValidService()) {
      // Get the service.
      $service = $entity->getService();
      // Get the service label and summary.
      $service_label = $service->label();
      $service_summary = $service->summary();
    }
    else {
      // Set the service label to broken.
      $service_label = $this->t('Broken');
      $service_summary = '';
    }
    // Build the row for the current entity.
    $filepath = '/' . drupal_get_path('module', 'search_api') . '/images/';
    $status = $entity->status() ? 'enabled' : 'disabled';
    return array(
      'status' => "<img src='$filepath$status.png' alt='$status' title='$status'></div>",
      'type' => "<div class=\"description\">Server</div>",
      'title' => String::checkPlain($entity->label()) . ($entity->getDescription() ? "<div class=\"description\">{$entity->getDescription()}</div>" : ''),
      'service' => String::checkPlain($service_label) . ($service_summary ? "<div class=\"description\">{$service_summary}</div>" : ''),
      'operations' => array(
        'data' => $this->buildOperations($entity),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Load the entities.
    $entities = $this->load();
    // Initialize the build variable to an empty array.
    $build = array(
      'enabled' => array('#markup' => "<h2>{$this->t('Enabled')}</h2>"),
      'disabled' => array('#markup' => "<h2>{$this->t('Disabled')}</h2>"),
    );
    // Iterate through the entity states.
    foreach (array('enabled', 'disabled') as $status) {
      // Initialize the rows variable to an empty array.
      $rows = array();
      // Iterate through the entities.
      foreach ($entities[$status] as $entity) {
        // Add the entity to the rows.
        $rows[$entity->id()] = $this->buildRow($entity);
      }
      // Build the status container.
      $build[$status]['#type'] = 'container';
      $build[$status]['table'] = array(
        '#theme' => 'table',
        '#header' => $this->buildHeader(),
        '#rows' => $rows,
      );
    }
    // Configure the empty messages.
    $build['enabled']['table']['#empty'] = $this->t('There are no enabled search servers.');
    $build['disabled']['table']['#empty'] = $this->t('There are no disabled search servers.');
    // Return the renderable array.
    return $build;
  }

}
