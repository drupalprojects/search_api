<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\ServerListController.
 */

namespace Drupal\search_api\Controller;

/*
 * Include required classes and interfaces.
 */
use Drupal\Core\Config\Entity\ConfigEntityListController;
use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;

/**
 * Defines a list controller for the Server entity.
 */
class ServerListController extends ConfigEntityListController {

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
      'title' => t('Name'),
      'description' => array(
        'data' => t('Description'),
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
      ),
      'operations' => t('Operations'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(\Drupal\Core\Entity\EntityInterface $entity) {
    return array(
      'title' => String::checkPlain($entity->label()),
      'description' => Xss::filterAdmin($entity->getDescription()),
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
