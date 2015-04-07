<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Processor\ContentAccessTest.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Utility;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\ContentAccess
 */
class ContentAccessTest extends ProcessorTestBase {

  use CommentTestTrait;

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\search_api\processor\ContentAccess
   */
  protected $processor;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * The comments created for testing.
   *
   * @var \Drupal\comment\Entity\Comment[]
   */
  protected $comments;

  /**
   * Performs setup tasks before each individual test method is run.
   */
  public function setUp() {
    parent::setUp('content_access');

    // The parent method already installs most needed node and comment schemas,
    // but here we also need the comment statistics.
    $this->installSchema('comment', array('comment_entity_statistics'));

    // Create a node type for testing.
    $type = NodeType::create(array('type' => 'page', 'name' => 'page'));
    $type->save();

    // Create anonymous user role.
    $role = Role::create(array(
      'id' => 'anonymous',
      'label' => 'anonymous',
    ));
    $role->save();

    // Insert the anonymous user into the database, as the user table is inner
    // joined by \Drupal\comment\CommentStorage.
    User::create(array(
      'uid' => 0,
      'name' => '',
    ))->save();

    // Create a node with attached comment.
    $this->nodes[0] = Node::create(array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[0]->save();

    $comment_type = CommentType::create(array(
      'id' => 'comment',
      'target_entity_type_id' => 'node',
    ));
    $comment_type->save();

    $this->installConfig(array('comment'));
    $this->addDefaultCommentField('node', 'page');

    $comment = Comment::create(array(
      'entity_type' => 'node',
      'entity_id' => $this->nodes[0]->id(),
      'field_name' => 'comment',
      'body' => 'test body',
      'comment_type' => $comment_type->id(),
    ));
    $comment->save();

    $this->comments[] = $comment;

    $this->nodes[1] = Node::create(array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[1]->save();

    // Also index users, to verify that they are unaffected by the processor.
    $this->index->set('datasources', array('entity:comment', 'entity:node', 'entity:user'));
    $fields = $this->index->getOption('fields');
    $fields['search_api_node_grants'] = array(
      'type' => 'string',
    );
    $fields['search_api_node_grants'] = array(
      'type' => 'string',
    );
    $this->index->setOption('fields', $fields);
    $this->index->save();

    $this->index = entity_load('search_api_index', $this->index->id(), TRUE);
  }

  /**
   * Tests building the query when content is accessible to all.
   */
  public function testQueryAccessAll() {
    user_role_grant_permissions('anonymous', array('access content', 'access comments'));
    $this->index->index();
    $query = Utility::createQuery($this->index);
    $result = $query->execute();

    $this->assertEqual($result->getResultCount(), 4);
  }

  /**
   * Tests building the query when content is accessible based on node grants.
   */
  public function estQueryAccessWithNodeGrants() {
    // Create user that will be passed into the query.
    $authenticated_user = $this->createUser(array('uid' => 2), array('access content'));

    db_insert('node_access')
      ->fields(array(
        'nid' => $this->nodes[0]->id(),
        'langcode' => $this->nodes[0]->language()->getId(),
        'gid' => $authenticated_user->id(),
        'realm' => 'search_api_test',
        'grant_view' => 1,
      ))
      ->execute();

    $this->index->index();
    $query = Utility::createQuery($this->index);
    $query->setOption('search_api_access_account', $authenticated_user);
    $result = $query->execute();

    $this->assertEqual($result->getResultCount(), 3);
  }

  /**
   *  Tests comment indexing when all users have access to content.
   */
  public function estContentAccessAll() {
    user_role_grant_permissions('anonymous', array('access content', 'access comments'));
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'text' => $this->randomMachineName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);

    foreach ($items as $item) {
      $this->assertEqual($item->getField('search_api_node_grants')->getValues(), array('node_access__all'));
    }
  }

  /**
   * Tests comment indexing when hook_node_grants() takes effect.
   */
  public function estContentAccessWithNodeGrants() {
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'field_text' => $this->randomMachineName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);

    foreach ($items as $item) {
      $this->assertEqual($item->getField('search_api_node_grants')->getValues(), array('node_access_search_api_test:0'));
    }
  }

}
