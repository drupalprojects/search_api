<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\CommentAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\NodeAccess;

/**
 * Adds node access information to comment indexes.
 */
class CommentAccess extends NodeAccess {
  
  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    return $index->getDatasource()->pluginId === 'entity:comment';
  }
  
  /**
   * Overrides SearchApiAlterNodeAccess::getNode().
   *
   * Returns the comment's node, instead of the item (i.e., the comment) itself.
   */
  protected function getNode($item) {
    return node_load($item->nid);
  }
  
  /**
   * {@inheritdoc}
   *
   * Doesn't index the comment's "Author".
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $old_status = !empty($form_state['index']->options['data_alter_callbacks']['search_api_alter_comment_access']['status']);
    $new_status = !empty($form_state['values']['callbacks']['search_api_alter_comment_access']['status']);

    if (!$old_status && $new_status) {
      $form_state['index']->options['fields']['status']['type'] = 'boolean';
    }

    return parent::submitConfigurationForm($form, $form_state);
  }
}