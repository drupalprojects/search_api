<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\SearchApiController.
 *
 * Overview page for Servers and Indexes. Since those are entities, entity list is used for displaying those.
 */

namespace Drupal\search_api\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines a list builder for the Server and Index entities.
 */
class SearchApiController extends ControllerBase {

  /**
   * Retrieves an array of all servers and indexes, ordered by status.
   *
   * @return \Drupal\Core\Entity\EntityInterface[][]
   *   An array with two keys, "enabled" and "disabled". Each of these contain
   *   a numeric array of entities, with servers being followed by all indexes
   *   assigned to them.
   */
  public function load() {
    // Initialize the entities variable to default array structure.
    $entities = array(
      'enabled' => array(),
      'disabled' => array(),
    );
    // Iterate over all indexes.
    $server_indexes = array();
    $indexes = $this->entityManager()->getStorageController('search_api_index')->loadMultiple();
    foreach ($indexes as $index) {
      /** @var $index \Drupal\search_api\Index\IndexInterface */
      // Add the entity to the list.
      $server_indexes[$index->getServerId()][] = $index;
    }
    // Iterate over all servers.
    $servers = $this->entityManager()->getStorageController('search_api_server')->loadMultiple();
    foreach ($servers as $server) {
      /** @var $server \Drupal\search_api\Server\ServerInterface */
      // Get the status key based upon the entity status.
      $status_key = $server->status() ? 'enabled' : 'disabled';
      // Add the entity to the list.
      $entities[$status_key][] = $server;
      if (isset($server_indexes[$server->id()])) {
        $entities[$status_key] = array_merge($entities[$status_key], $server_indexes[$server->id()]);
      }
    }

    // Add indexes which aren't bound to any server (orphans).
    if (isset($server_indexes[''])) {
      $entities['disabled'] = array_merge($entities['disabled'], $server_indexes[NULL]);
    }

    return $entities;
  }

  /**
   * Returns the header to use for the overview table.
   */
  public function buildHeader() {
    return array(
      'status' => $this->t('Status'),
      'type' => array('data' => $this->t('Type'), 'colspan' => 2),
      'title' => $this->t('Name'),
      'operations' => $this->t('Operations'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(ConfigEntityInterface $entity) {
    $row = array();
    $status = $entity->status() ? 'enabled' : 'disabled';
    $status_label = String::checkPlain($entity->status() ? $this->t('Enabled') : $this->t('Disabled'));
    $row[] = "<span class=\"search-api-entity-status-$status\" title=\"$status_label\"><span class=\"visually-hidden\">$status_label</span></span>";
    if ($entity instanceof ServerInterface) {
      $type = 'server';
      $row[] = array('data' => $this->t('Server'), 'colspan' => 2);
    }
    elseif ($entity instanceof IndexInterface) {
      $type = 'index';
      if ($entity->hasValidServer()) {
        $row[] = '';
        $row[] = $this->t('Index');
      }
      else {
        $row[] = array('data' => $this->t('Index'), 'colspan' => 2);
      }
    }
    else {
      return array();
    }
    $url = $entity->urlInfo();
    $row[] = $this->l($entity->label(), $url['route_name'], $url['route_parameters'], $url['options']);

    $local_tasks = \Drupal::service('plugin.manager.menu.local_task')->getDefinitions();
    foreach ($local_tasks as $plugin_id => $local_task) {
      if ($local_task['base_route'] == "search_api.{$type}_view") {
        $operations[$plugin_id] = array(
          'title' => $this->t($local_task['title']),
          'route_name' => $local_task['route_name'],
        ) + $url;
      }
    }

    if ($entity->status()) {
      $operations['disable'] = array(
        'title' => $this->t('Disable'),
        'route_name' => "search_api.{$type}_disable",
      ) + $url;
    }
    elseif (!($entity instanceof IndexInterface) || $entity->hasValidServer()) {
      $operations['enable'] = array(
        'title' => $this->t('Enable'),
        'route_name' => "search_api.{$type}_enable",
      ) + $url;
    }

    $operations['delete'] = array(
      'title' => $this->t('Delete'),
      'route_name' => "search_api.{$type}_delete",
    ) + $url;
    $this->moduleHandler()->alter('entity_operation', $operations, $entity);
    $row[] = array(
      'data' => array(
        '#type' => 'operations',
        '#links' => $operations,
      ),
    );

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function overview() {
    // Load the entities.
    $entities = $this->load();
    // Initialize the build variable to an empty array.
    $build = array(
      'enabled' => array('#markup' => '<h2>' . $this->t('Enabled servers') . '</h2>'),
      'disabled' => array('#markup' => '<h2>' . $this->t('Disabled configuration') . '</h2>'),
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
    // Add CSS.
    $build['#attached']['css'][] = drupal_get_path('module', 'search_api') . '/search_api.admin.css';
    // Configure the empty messages.
    $build['enabled']['table']['#empty'] = $this->t('There are no enabled search servers.');
    $build['disabled']['table']['#empty'] = $this->t('There are no disabled search servers or indexes.');
    // Return the renderable array.
    return $build;
  }

}
