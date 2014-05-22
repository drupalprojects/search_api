<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiContentAccessProcessorTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\Query;

/**
 * Tests the ContentAccess processor.
 */
class SearchApiContentAccessProcessorTest extends SearchApiProcessorTestBase {

  /**
   * @var \Drupal\comment\Entity\Comment[]
   */
  protected $comments;

  /**
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => '"Content access" tests',
      'description' => 'Tests if the "Content access" processor works correctly.',
      'group' => 'Search API',
    );
  }

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp() {
    parent::setUp('search_api_content_access_processor');

    // Create a node type for testing.
    $type = entity_create('node_type', array('type' => 'page', 'name' => 'page'));
    $type->save();

    // Create anonymous user name.
    $role = entity_create('user_role', array(
      'id' => 'anonymous',
      'label' => 'anonymous',
    ));
    $role->save();

    // Insert anonymous user into the database as the user table is inner joined
    // by the CommentStorage.
    $anonymous_user = new AnonymousUserSession();
    entity_create('user', array(
      'uid' => $anonymous_user->id(),
      'name' => $anonymous_user->getUsername(),
    ))->save();

    // Create a node with attached comment.
    $this->nodes[0] = entity_create('node', array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[0]->save();
    entity_reference_create_instance('node', 'page', 'comments', 'Comments', 'comment');
    $comment = entity_create('comment', array('entity_type' => 'node', 'entity_id' => $this->nodes[0]->id(), 'field_id' => 'node__comments', 'body' => 'test body'));
    $comment->save();

    $this->comments[] = $comment;

    $this->nodes[1] = entity_create('node', array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[1]->save();

    $fields = $this->index->getOption('fields');
    $fields['entity:node|search_api_node_grants'] = array(
      'type' => 'string',
    );
    $fields['entity:comment|search_api_node_grants'] = array(
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
    $query = Query::create($this->index);
    $result = $query->execute();

    $this->assertEqual($result['result count'], 2, 'The result should contain all items');
  }

  /**
   * Tests building the query when content is accessible based on node grants.
   */
  public function testQueryAccessWithNodeGrants() {
    // Create user that will be passed into the query.
    $authenticated_user = $this->createUser(array('uid' => 2), array('access content'));

    db_insert('node_access')
      ->fields(array(
        'nid' => $this->nodes[0]->id(),
        'langcode' => $this->nodes[0]->language()->id,
        'gid' => $authenticated_user->id(),
        'realm' => 'search_api_test',
        'grant_view' => 1,
      ))
      ->execute();

    $this->index->index();
    $query = Query::create($this->index);
    $query->setOption('search_api_access_account', $authenticated_user);
    $result = $query->execute();

    $this->assertEqual($result['result count'], 1, 'The result should contain only one item to which the user has granted access');
  }

  /**
   *  Test scenario all users have access to content.
   */
  public function testContentAccessAll() {
    user_role_grant_permissions('anonymous', array('access content', 'access comments'));
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment,
        'item_id' => $comment->id(),
        'text' => $this->randomName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);
    foreach ($items as $item) {
      $this->assertEqual($item['entity:comment' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'search_api_node_grants']['value'], array('node_access__all'));
    }
  }

  /**
   * Tests scenario where hook_search_api_node_grants() take effect.
   */
  public function testContentAccessWithNodeGrants() {
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment,
        'item_id' => $comment->id(),
        'field_text' => $this->randomName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);
    foreach ($items as $item) {
      $this->assertEqual($item['entity:comment' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'search_api_node_grants']['value'], array('node_access_search_api_test:0'));
    }
  }

}
