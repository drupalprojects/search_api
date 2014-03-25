<?php
/**
 * @file
 * Contains \Drupal\search_api\ServerListBuilder.
 *
 * Overview page for Servers and Indexes.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Component\Utility\String;

/**
 * Defines a list controller for the Server entity.
 */
class ServerListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    // Initialize the entities variable to default array structure.
    $entities = array(
      'enabled' => array(),
      'disabled' => array(),
    );
    // Iterate through indexes.
    $indexes = array();
    foreach (entity_load_multiple('search_api_index') as $entity) {
      // Add the entity to the list.
      $indexes[$entity->serverMachineName][$entity->machine_name] = $entity;
    }
    // Iterate through servers.
    foreach (parent::load() as $entity) {
      // Get the status key based upon the entity status.
      $status_key = $entity->status() ? 'enabled' : 'disabled';
      // Add the entity to the list.
      $entities[$status_key][$entity->machine_name] = $entity;
      if (isset($indexes[$entity->machine_name])) {
        $entities[$status_key] += $indexes[$entity->machine_name];
      }
    }

    // Add indexes which aren't bound to any server (orphans).
    if (isset($indexes[''])) {
      $entities['disabled'] += $indexes[''];
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return array(
      'status' => $this->t('Status'),
      'type' => array('data' => $this->t('Type'), 'colspan' => 2),
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
    if ($entity->getEntityTypeId() == 'search_api_server') {
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
    }
    else {
      // None for indexes.
      $service_label = '';
      $service_summary = '';
    }
    // Build the row for the current entity.
    $filepath = '/' . drupal_get_path('module', 'search_api') . '/images/';
    $status = $entity->status() ? 'enabled' : 'disabled';
    $type = $entity->getEntityTypeId() == 'search_api_server' ? 'Server' : 'Index';
    $row['status'] = "<img src='$filepath$status.png' alt='$status' title='$status'></div>";
    if ($type == 'Index' && !empty($entity->serverMachineName)) {
      $row[''] = '';
      $row['type'] = $type;
    }
    else {
      $row['type'] = array('data' => $type, 'colspan' => 2);
    }
    $label = l($entity->label(), "admin/config/search/search_api/" . drupal_strtolower($type) . "/$entity->machine_name/edit");
    $row['title'] = $label . ($entity->getDescription() ? "<div class=\"description\">{$entity->getDescription()}</div>" : '');
    $row['service'] = String::checkPlain($service_label) . ($service_summary ? "<div class=\"description\">{$service_summary}</div>" : '');
    $row['operations'] = array(
      'data' => $this->buildOperations($entity),
    );

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Load the entities.
    $entities = $this->load();
    // Initialize the build variable to an empty array.
    $build = array(
      'enabled' => array('#markup' => "<h2>{$this->t('Active servers')}</h2>"),
      'disabled' => array('#markup' => "<h2>{$this->t('Inactive elements')}</h2>"),
    );
    // Iterate through the entity states.
    foreach (array_keys($build) as $status) {
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
