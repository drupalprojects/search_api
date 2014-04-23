<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\CommentAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Index\IndexInterface;

/**
 * Adds node access information to comment indexes.
 */
class CommentAccess extends NodeAccess {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    // @todo Re-introduce Datasource::getEntityType() for this?
    foreach ($index->getDatasources() as $datasource) {
      $definition = $datasource->getPluginDefinition();
      if (isset($definition['entity_type']) && $definition['entity_type'] === 'comment') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Overrides \Drupal\search_api\Plugin\SearchApi\Processor\NodeAccess::getNode().
   *
   * Returns the comment's node, instead of the item (i.e., the comment) itself.
   */
  protected function getNode($item) {
    return node_load($item->nid);
  }

  /**
   * Overrides \Drupal\search_api\Plugin\SearchApi\Processor\NodeAccess::submitConfigurationForm().
   *
   * Doesn't index the comment's "Author".
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    // @todo Update – see parent class method.
    $old_status = !empty($form_state['index']->options['data_alter_callbacks']['search_api_alter_comment_access']['status']);
    $new_status = !empty($form_state['values']['callbacks']['search_api_alter_comment_access']['status']);

    if (!$old_status && $new_status) {
      $form_state['index']->options['fields']['entity:comment' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'status']['type'] = 'boolean';
    }

    parent::submitConfigurationForm($form, $form_state);
  }
}
