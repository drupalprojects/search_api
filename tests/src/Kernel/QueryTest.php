<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\Query;

/**
 * Tests query functionality.
 *
 * @group search_api
 */
class QueryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'search_api',
    'search_api_test',
    'language',
    'user',
    'system',
    'entity_test',
  );

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installEntitySchema('entity_test');

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    // Create a test server.
    $server = Server::create(array(
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
    ));
    $server->save();

    // Create a test index.
    Index::create(array(
      'name' => 'Test Index',
      'id' => 'test_index',
      'status' => 1,
      'datasource_settings' => array(
        'entity:entity_test' => array(
          'plugin_id' => 'entity:entity_test',
          'settings' => array(),
        ),
      ),
      'tracker_settings' => array(
        'default' => array(
          'plugin_id' => 'default',
          'settings' => array(),
        ),
      ),
      'server' => $server->id(),
      'options' => array('index_directly' => FALSE),
    ))->save();
    $this->index = Index::load('test_index');
  }

  /**
   * Tests that queries can be cloned.
   */
  public function testQueryCloning() {
    $query = $this->index->query();
    $this->assertEquals(0, $query->getResults()->getResultCount());
    $cloned_query = clone $query;
    $cloned_query->getResults()->setResultCount(1);
    $this->assertEquals(0, $query->getResults()->getResultCount());
    $this->assertEquals(1, $cloned_query->getResults()->getResultCount());
  }

  /**
   * Tests that serialization of queries works correctly.
   */
  public function testQuerySerialization() {
    $query = $this->prepareQuery();
    $cloned_query = clone $query;
    $unserialized_query = unserialize(serialize($query));
    $this->assertEquals($cloned_query, $unserialized_query);
  }

  /**
   * Prepares a query for testing purposes.
   *
   * @return \Drupal\search_api\Query\Query
   *   A search query.
   */
  protected function prepareQuery() {
    $results_cache = $this->container->get('search_api.results_static_cache');
    $query = Query::create($this->index, $results_cache);
    $tags = array('tag1', 'tag2');
    $query->keys('foo bar')
      ->addCondition('field1', 'value', '<')
      ->addCondition('field2', array(15, 25), 'BETWEEN')
      ->addConditionGroup($query->createConditionGroup('OR', $tags)
        ->addCondition('field2', 'foo')
        ->addCondition('field3', 1, '<>')
      )
      ->sort('field1', Query::SORT_DESC)
      ->sort('field2');
    $query->setOption('option1', array('foo' => 'bar'));
    $translation = $this->container->get('string_translation');
    $query->setStringTranslation($translation);
    return $query;
  }

}
