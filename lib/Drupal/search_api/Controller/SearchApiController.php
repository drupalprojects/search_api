<?php
/**
 * @file
 * Contains \Drupal\search_api\Controller\SearchApiController.
 *
 * Overview page for Servers and Indexes. Since those are entities, entity list is used for displaying those.
 */

namespace Drupal\search_api\Controller;

use Drupal\Core\Config\Entity\ConfigEntityBase;
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
    $indexes = $this->entityManager()->getStorage('search_api_index')->loadMultiple();
    $servers = $this->entityManager()->getStorage('search_api_server')->loadMultiple();

    $this->sortByStatusThenAlphabetically($servers);
    $this->sortByStatusThenAlphabetically($indexes);

    $serverGroups = array();

    foreach ($servers as $server) {
      $serverGroup = array(
        $server->id() => $server,
      );

      foreach ($server->getIndexes() as $index) {
        $serverGroup[$index->id()] = $index;
        unset($indexes[$index->id()]);
      }

      $serverGroups[$server->id()] = $serverGroup;
    }

    return array(
      'servers' => $serverGroups,
      'loneIndexes' => $indexes,
    );
  }

  /**
   * Sort array of entities first by status
   * then alphabetically.
   *
   * @param array Array of ConfigEntityBase $entities
   */
  protected function sortByStatusThenAlphabetically(array &$entities) {
    usort($entities, function (ConfigEntityBase $a, ConfigEntityBase $b) {
      if ($a->status() == $b->status()) {
        return $a->label() > $b->label();
      } else {
        return $a->status() ? -1 : 1;
      }
    });
  }

  /**
   * Returns the header to use for the overview table.
   */
  protected function buildHeader() {
    return array(
      'status' => $this->t('Status'),
      'type' => array('data' => $this->t('Type'), 'colspan' => 2),
      'title' => $this->t('Name'),
      'operations' => $this->t('Operations'),
    );
  }

  public function buildRow(ConfigEntityBase $entity, $nested = FALSE) {
    $row = array();
    $titleColspan = 2;
    $status = $entity->status() ? 'enabled' : 'disabled';
    $statusLabel = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $statusIcon = '<span class="search-api-entity-status-' . $status . '" title="' . $statusLabel . '"><span class="visually-hidden">' . $statusLabel . '</span></span>';

    $row[] = $statusIcon;
    if ($nested) {
      $row[] = '';
      $titleColspan = 1;
    }
    if ($entity instanceof ServerInterface) {
      $row[] = array('data' => $this->t('Server'), 'colspan' => $titleColspan);
    }
    elseif ($entity instanceof IndexInterface) {
      $row[] = array('data' => $this->t('Index'), 'colspan' => $titleColspan);
    }
    else {
      return array();
    }



    $url = $entity->urlInfo('canonical');
    $row[] = array('data' => array(
      '#type' => 'link',
      '#title' => $entity->label(),
      '#route_name' => $url['route_name'],
      '#route_parameters' => $url['route_parameters'],
      '#suffix' => '<div>' . $entity->get('description') . '</div>',
    ));

    $row[] = $this->buildOperations($entity);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function buildGroup($group) {
    static $tr_class = 'odd-group';
    $rows = array();

    if (is_array($group)) {
      foreach ($group as $entity) {
        $nested = $entity instanceof IndexInterface;
        $row['data'] = $this->buildRow($entity, $nested);
        // Add class with entity id to allow test this page easily
        $row['class'] = array($tr_class, $entity->getEntityTypeId() . '-' . $entity->id());
        $rows[] = $row;
      }
    }
    elseif ($group instanceof ConfigEntityBase) {
      $rows = array(array(
        'data' => $this->buildRow($group),
        'class' => array($tr_class),
      ));
    }

    $tr_class = ($tr_class == 'odd-group') ? 'even-group' : 'odd-group';

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function overview() {
    // Load the servers and indexes groups.
    $groups = $this->load();

    $rows = $build = array();

    // Iterate through the groups.
    foreach ($groups as $subGroup) {
      foreach ($subGroup as $group) {
        $rows = array_merge($rows, $this->buildGroup($group));
      }
    }

    $build['table'] = array(
      '#theme' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => $rows,
      '#attributes' => array(
        'id' => array('search-api-overview'),
      ),
    );

    // Add CSS.
    $build['#attached']['library'][] = 'search_api/drupal.search_api.overview';

    return $build;
  }

  protected function buildOperations(ConfigEntityBase $entity) {
    // Fetch
    $urlParams = $entity->urlInfo();
    $entityLinks = $entity->getEntityType()->getLinkTemplates();
    if ($entity->status()) {
      unset($entityLinks['enable']);
    }
    else {
      unset($entityLinks['disable']);
    }

    $operations = array();
    foreach ($entityLinks as $link => $routeName) {
      $operations[$link] = array(
        'title' => $link,
        'route_name' => $routeName,
      ) + $urlParams;
    }

    return array(
      'data' => array(
        '#type' => 'operations',
        '#links' => $operations,
      ),
    );
  }
}
