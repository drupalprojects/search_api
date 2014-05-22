<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\ContentAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * @SearchApiProcessor(
 *   id = "search_api_content_access_processor",
 *   label = @Translation("Content access processor"),
 *   description = @Translation("Adds access information to node and comment indexes")
 * )
 */
class ContentAccess extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), array('node', 'comment'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if (!$datasource) {
      return;
    }

    if (in_array($datasource->getEntityTypeId(), array('node', 'comment'))) {
      $definition = array(
        'label' => t('Node access information'),
        'description' => t('Data needed to apply node access.'),
        'type' => 'string',
      );
      $properties['search_api_node_grants'] = new DataDefinition($definition);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    static $account;

    if (!isset($account)) {
      // Load the anonymous user.
      $account = new AnonymousUserSession();
    }

    foreach ($items as $id => $item) {
      // Only run for node and comment items.
      if (!in_array($this->index->getDatasource($item['#datasource'])->getEntityTypeId(), array('node', 'comment'))) {
        continue;
      }

      // Bail if the field is not indexed.
      $field = $item['#datasource'] . IndexInterface::DATASOURCE_ID_SEPARATOR . 'search_api_node_grants';
      if (empty($items[$id][$field])) {
        continue;
      }

      $node = $this->getNode($item['#item']);
      if (!$node) {
        // Apparently we were active for a wrong item.
        continue;
      }

      // Collect grant information for item.
      if (!$node->access('view', $account)) {
        // If anonymous user has no permission we collect all grants with their
        // realms in the item.
        $result = db_query('SELECT * FROM {node_access} WHERE (nid = 0 OR nid = :nid) AND grant_view = 1', array(':nid' => $node->id()));
        foreach ($result as $grant) {
          $items[$id][$field]['value'][] = "node_access_{$grant->realm}:{$grant->gid}";
        }
      }
      else {
        // Add the generic pseudo view grant if we are not using node access or
        // the node is viewable by anonymous users.
        $items[$id][$field]['value'][] = 'node_access__all';
      }
    }
  }

  /**
   * Retrieves the node related to a search item.
   *
   * Will be either the node itself, or the node the comment is attached to.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $item
   *
   * @return \Drupal\node\NodeInterface
   */
  protected function getNode(ContentEntityInterface $item) {
    if ($item instanceof CommentInterface) {
      $item = $item->getCommentedEntity();
    }
    if ($item instanceof NodeInterface) {
      return $item;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    // @todo - here we need to secure that the author and published fields are
    //   being indexed as we need it for the access filter.

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $processors = $this->index->getOption('processors');

    if (!empty($processors['search_api_content_access_processor']['status']) && !$query->getOption('search_api_bypass_access')) {
      $account = $query->getOption('search_api_access_account', \Drupal::currentUser());
      if (is_numeric($account)) {
        $account = user_load($account);
      }
      if (is_object($account)) {
        try {
          $this->queryAddNodeAccess($account, $query);
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
      else {
        watchdog('search_api', 'An illegal user UID was given for node access: @uid.', array('@uid' => $query->getOption('search_api_access_account', \Drupal::currentUser())), WATCHDOG_WARNING);
      }
    }
  }

  /**
   * Adds a node access filter to a search query, if applicable.
   *
   * @param \Drupal\core\Session\AccountInterface $account
   *   The user object, who searches.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to which a node access filter should be added, if applicable.
   *
   * @throws SearchApiException
   *   If not all necessary fields are indexed on the index.
   */
  protected function queryAddNodeAccess(AccountInterface $account, QueryInterface $query) {
    // Don't do anything if the user can access all content.
    if ($account->hasPermission('bypass node access')) {
      return;
    }

    // Get the fields that are being indexed.
    $fields = $query->getIndex()->getOption('fields');
    // Define required fields that needs to be part of the index.
    $required = array('entity:node|search_api_node_grants', 'entity:node|status');

    $datasources_filter = $query->createFilter('OR');

    // Go through the datasources and add conditions for status and author.
    foreach ($this->index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
        $node_filter = $query->createFilter('OR');
        $node_filter->condition('entity:node|status', NODE_PUBLISHED);
        if (\Drupal::currentUser()->hasPermission('view own unpublished content')) {
          $node_filter->condition('entity:node|author', $account->id());
        }
        $datasources_filter->filter($node_filter);
        // Add author as one of the required fields as we need to add query
        // condition for the field.
        $required[] = 'entity:node|author';
      }
      elseif ($datasource->getEntityTypeId() == 'comment') {
        $datasources_filter->condition('entity:comment|status', Comment::PUBLISHED);
      }
    }

    $query->filter($datasources_filter);

    // Check if all required fields are present in the index.
    foreach ($required as $field) {
      if (empty($fields[$field])) {
        $vars['@field'] = $field;
        $vars['@index'] = $query->getIndex()->label();
        throw new SearchApiException(t('Required field @field not indexed on index @index. Could not perform access checks.', $vars));
      }
    }

    // If the user cannot access content/comments at all, return no results.
    if (!$account->hasPermission('access content')) {
      // Simple hack for returning no results.
      $query->condition('entity:node|status', 0);
      $query->condition('entity:node|status', 1);
      return;
    }

    // Filter by node access grants.
    // Check items for the grants of account previously collected.
    // They are only present for items with limited access.
    $node_filter = $query->createFilter('OR');
    $grants = node_access_grants('view', $account);
    foreach ($grants as $realm => $gids) {
      foreach ($gids as $gid) {
        $node_filter->condition('entity:node|search_api_node_grants', "node_access_$realm:$gid");
      }
    }
    // Also add items that are accessible for anonymous user by checking the
    // pseudo view grant.
    $node_filter->condition('entity:node|search_api_node_grants', 'node_access__all');
    $query->filter($node_filter);
  }

}
