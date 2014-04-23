<?php
/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\NodeAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * @SearchApiProcessor(
 *   id = "search_api_node_access_processor",
 *   label = @Translation("Node access processor"),
 *   description = @Translation("Adds node access information to node indexes")
 * )
 */
class NodeAccess extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    // @todo Re-introduce Datasource::getEntityType() for this?
    foreach ($index->getDatasources() as $datasource) {
      $definition = $datasource->getPluginDefinition();
      if (isset($definition['entity_type']) && $definition['entity_type'] === 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource) {
    $datasource_definition = $datasource->getPluginDefinition();
    if (isset($datasource_definition['entity_type']) && $datasource_definition['entity_type'] === 'node') {
      $definition = array(
        'label' => t('Node access information'),
        'description' => t('Data needed to apply node access.'),
        'type' => 'string',
      );
      $properties['search_api_access_node'] = new DataDefinition($definition);
    }
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

    foreach ($items as $id => $item) {
      // @todo Only process nodes (check '#datasource' key).
      /** @var $node \Drupal\Node\NodeInterface */
      $node = $this->getNode($item);
      // Check whether all users have access to the node.
      if (!$node->access('view', $account)) {
        // Get node access grants.
        $result = db_query('SELECT * FROM {node_access} WHERE (nid = 0 OR nid = :nid) AND grant_view = 1', array(':nid' => $node->id()));

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
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
   return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Overrides \Drupal\search_api\Processor\ProcessorPluginBase::submitConfigurationForm().
   *
   * If the data alteration is being enabled, sets "Published" and "Author" to
   * "indexed", because both are needed for the node access filter.
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    // @todo This code is outdated. We need a new, preferably cleaner way to
    // determine whether the processor was just enabled (and to mark field as
    // required).
    $old_status = !empty($form_state['index']->options['data_alter_callbacks']['search_api_alter_node_access']['status']);
    $new_status = !empty($form_state['values']['callbacks']['search_api_alter_node_access']['status']);

    if (!$old_status && $new_status) {
      $form_state['index']->options['fields']['entity:node' . IndexInterface::FIELD_ID_SEPARATOR . 'status']['type'] = 'boolean';
      $form_state['index']->options['fields']['entity:node' . IndexInterface::FIELD_ID_SEPARATOR . 'author']['type'] = 'integer';
      $form_state['index']->options['fields']['entity:node' . IndexInterface::FIELD_ID_SEPARATOR . 'author']['entity_type'] = 'user';
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    // @todo Implement.
  }

}
