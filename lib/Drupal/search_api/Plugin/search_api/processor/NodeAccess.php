<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\NodeAccess.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Adds node access information to node indexes.
 *
 * @SearchApiProcessor(
 *   id = "search_api_node_access",
 *   name = @Translation("Node access"),
 *   description = @Translation("Add node access information to the index."),
 *   weight = -10
 * )
 */
class NodeAccess extends ProcessorPluginBase {

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * Returns TRUE only for indexes on nodes.
   */
  public static function supportsIndex(IndexInterface $index) {
    // Currently only node access is supported.
    return $index->getEntityType() === 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $old_status = !empty($form_state['index']->options['data_alter_callbacks']['search_api_alter_node_access']['status']);
    $new_status = !empty($form_state['values']['callbacks']['search_api_alter_node_access']['status']);

    if (!$old_status && $new_status) {
      $form_state['index']->options['fields']['status']['type'] = 'boolean';
      $form_state['index']->options['fields']['author']['type'] = 'integer';
      $form_state['index']->options['fields']['author']['entity_type'] = 'user';
    }

    return parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function propertyInfo() {
    return array(
      'search_api_access_node' => array(
        'label' => t('Node access information'),
        'description' => t('Data needed to apply node access.'),
        'type' => 'list<token>',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    static $account;

    if (!isset($account)) {
      // Load the anonymous user.
      $account = drupal_anonymous_user();
    }

    foreach ($items as $nid => &$item) {
      // Check whether all users have access to the node.
      if (!node_access('view', $item, $account)) {
        // Get node access grants.
        $result = db_query('SELECT * FROM {node_access} WHERE (nid = 0 OR nid = :nid) AND grant_view = 1', array(':nid' => $item->nid));

        // Store all grants together with it's realms in the item.
        foreach ($result as $grant) {
          if (!isset($items[$nid]->search_api_access_node)) {
            $items[$nid]->search_api_access_node = array();
          }
          $items[$nid]->search_api_access_node[] = "node_access_$grant->realm:$grant->gid";
        }
      }
      else {
        // Add the generic view grant if we are not using node access or the
        // node is viewable by anonymous users.
        $items[$nid]->search_api_access_node = array('node_access__all');
      }
    }
  }

}
