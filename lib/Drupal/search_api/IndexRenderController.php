<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexRenderController.
 */

namespace Drupal\search_api;

use Drupal\Core\Entity\EntityRenderControllerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a search index render controller.
 */
class IndexRenderController implements EntityRenderControllerInterface {

  /**
   * {@inheritdoc}
   */
  public function buildContent(array $entities, array $displays, $view_mode, $langcode = NULL) {
    // @todo Is there anything we should do here?
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $build = $this->viewMultiple(array($entity->id() => $entity), $view_mode, $langcode);
    return reset($build);
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultiple(array $entities = array(), $view_mode = 'full', $langcode = NULL) {
    $build = array();
    foreach ($entities as $entity) {
      // @todo Implement.
    }
    return $build;
  }

}
