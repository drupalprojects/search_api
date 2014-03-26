<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\NodeAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds node access information to node indexes.
 */
class NodeAccess extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    // Currently only node access is supported.
    return $index->getEntityType() === 'node';
  }

  /**
   * Overrides SearchApiAbstractAlterCallback::propertyInfo().
   *
   * Adds the "search_api_access_node" property.
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
  public function alterItems(array &$items) {
    static $account;

    if (!isset($account)) {
      // Load the anonymous user.
      $account = drupal_anonymous_user();
    }

    foreach ($items as $id => $item) {
      /** @var $node \Drupal\Node\NodeInterface */
      $node = $this->getNode($item);
      // Check whether all users have access to the node.
      if (!$node->access('view', $account)) {
        // Get node access grants.
        $result = db_query('SELECT * FROM {node_access} WHERE (nid = 0 OR nid = :nid) AND grant_view = 1', array(':nid' => $node->nid));

        // Store all grants together with their realms in the item.
        foreach ($result as $grant) {
          $items[$id]->search_api_access_node[] = "node_access_{$grant->realm}:{$grant->gid}";
        }
      }
      else {
        // Add the generic view grant if we are not using node access or the
        // node is viewable by anonymous users.
        $items[$id]->search_api_access_node = array('node_access__all');
      }
    }
  }

  /**
   * Retrieves the node related to a search item.
   *
   * In the default implementation for nodes, the item is already the node.
   * Subclasses may override this to easily provide node access checks for
   * items related to nodes.
   */
  protected function getNode($item) {
    return $item;
  }

  /**
   * {@inheritdoc}
   *
   * If the data alteration is being enabled, set "Published" and "Author" to
   * "indexed", because both are needed for the node access filter.
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

}
