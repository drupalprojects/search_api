<?php

/**
 * @file
 * Contains \Drupal\search_api_db\Tests\SearchApiDbTest.
 */

namespace Drupal\search_api_db\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\search_api\Index\IndexInterface;
use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Tests index and search capabilities using the Database search backend.
 */
class SearchApiDbTest extends EntityUnitTestBase {

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field', 'search_api', 'search_api_db');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Test "Database search" module',
      'description' => 'Tests indexing and searching with the "Database search" module.',
      'group' => 'Search API',
    );
  }

  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installSchema('system', array('router'));
    $this->installSchema('user', array('users_data'));

    // Create the required bundles.
    entity_test_create_bundle('item');
    entity_test_create_bundle('article');

    // Create a 'body' field on the test entity type.
    entity_create('field_config', array(
      'name' => 'body',
      'entity_type' => 'entity_test',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => 'body',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Body',
      'settings' => array('display_summary' => TRUE),
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => 'body',
      'entity_type' => 'entity_test',
      'bundle' => 'article',
      'label' => 'Body',
      'settings' => array('display_summary' => TRUE),
    ))->save();

    // Create a 'keywords' field on the test entity type.
    entity_create('field_config', array(
      'name' => 'keywords',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => 'keywords',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Keywords',
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => 'keywords',
      'entity_type' => 'entity_test',
      'bundle' => 'article',
      'label' => 'Keywords',
    ))->save();
  }

  /**
   * Tests various indexing scenarios for the Database search backend.
   */
  public function testFramework() {
    $this->insertItems();
    $this->createServer();
    $this->createIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems();
    $this->searchSuccess1();
    $this->checkFacets();
    $this->regressionTests();
    $this->editServer();
    $this->searchSuccess2();
    $this->clearIndex();
    $this->searchNoResults();
    $this->regressionTests2();
    $this->uninstallModule();
  }

  protected function insertItems() {
    $count = \Drupal::entityQuery('entity_test')->count()->execute();

    entity_create('entity_test', array(
      'id' => 1,
      'name' => 'foo bar baz',
      'body' => 'test test',
      'type' => 'item',
      'keywords' => array('orange'),
    ))->save();
    entity_create('entity_test', array(
      'id' => 2,
      'name' => 'foo test',
      'body' => 'bar test',
      'type' => 'item',
      'keywords' => array('orange', 'apple', 'grape'),
    ))->save();
    entity_create('entity_test', array(
      'id' => 3,
      'name' => 'bar',
      'body' => 'test foobar',
      'type' => 'item',
    ))->save();
    entity_create('entity_test', array(
      'id' => 4,
      'name' => 'foo baz',
      'body' => 'test test test',
      'type' => 'article',
      'keywords' => array('apple', 'strawberry', 'grape'),
    ))->save();
    entity_create('entity_test', array(
      'id' => 5,
      'name' => 'bar baz',
      'body' => 'foo',
      'type' => 'article',
      'keywords' => array('orange', 'strawberry', 'grape', 'banana'),
    ))->save();
    $count = \Drupal::entityQuery('entity_test')->count()->execute() - $count;
    $this->assertEqual($count, 5, "$count items inserted.");
  }

  protected function createServer() {
    $this->serverId = 'database_search_server';
    $values = array(
      'name' => 'Database search server',
      'machine_name' => $this->serverId,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backendPluginId' => 'search_api_db',
      'backendPluginConfig' => array(
        'min_chars' => 3,
        'database' => 'default:default',
      ),
    );
    $success = (bool) entity_create('search_api_server', $values)->save();
    $this->assertTrue($success, 'The server was successfully created.');
  }

  protected function createIndex() {
    $this->indexId = 'test_index';
    $values = array(
      'name' => 'Test index',
      'machine_name' => $this->indexId,
      'datasourcePluginIds' => array('entity:entity_test'),
      'trackerPluginId' => 'default_tracker',
      'status' => 1,
      'description' => 'An index used for testing.',
      'serverMachineName' => $this->serverId,
      'options' => array(
        'cron_limit' => -1,
        'index_directly' => TRUE,
        'fields' => array(
          $this->getFieldId('id') => array(
            'type' => 'integer',
          ),
          $this->getFieldId('name') => array(
            'type' => 'text',
            'boost' => '5.0',
          ),
          $this->getFieldId('body') => array(
            'type' => 'text',
          ),
          $this->getFieldId('type') => array(
            'type' => 'string',
          ),
          $this->getFieldId('keywords') => array(
            'type' => 'string',
          ),
        ),
      ),
    );

    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_create('search_api_index', $values);
    $success = (bool) $index->save();
    $this->assertTrue($success, 'The index was successfully created.');
    $this->assertEqual($index->getTracker()->getTotalItemsCount(), 5, 'Correct item count.');
    $this->assertEqual($index->getTracker()->getIndexedItemsCount(), 0, 'All items still need to be indexed.');
  }

  protected function updateIndex() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId);

    // Remove a field from the index and check if the change is matched in
    // the server configuration.
    unset($index->options['fields'][$this->getFieldId('keywords')]);
    $index->save();

    /** @var \Drupal\search_api\Server\ServerInterface $server*/
    $server = entity_load('search_api_server', $this->serverId, TRUE);
    $index_fields = array_keys($index->options['fields']);
    $server_fields = array_keys($server->backendPluginConfig['field_tables'][$index->id()]);
    sort($index_fields);
    sort($server_fields);
    $this->assertEqual($index_fields, $server_fields);

    // Add the field back for the next assertions.
    $index->options['fields'][$this->getFieldId('keywords')] = array(
      'type' => 'string',
    );
    $index->save();
  }

  protected function buildSearch($keys = NULL, array $filters = array(), array $fields = array()) {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId);
    $query = $index->query();
    if ($keys) {
      $query->keys($keys);
      if ($fields) {
        $query->fields($fields);
      }
    }
    foreach ($filters as $filter) {
      list($field, $value) = explode(',', $filter, 2);
      $query->condition($this->getFieldId($field), $value);
    }
    $query->range(0, 10);

    return $query;
  }

  protected function searchNoResults() {
    $results = $this->buildSearch('test')->execute();
    $this->assertEqual($results['result count'], 0, 'No search results returned without indexing.');
    $this->assertEqual(array_keys($results['results']), array(), 'No search results returned without indexing.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');
  }

  protected function indexItems() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId);
    $index->index();
  }

  protected function searchSuccess1() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEqual($results['result count'], 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(4, 1)), 'Search for »test« returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch('"test foo"')->execute();
    $this->assertEqual($results['result count'], 3, 'Search for »"test foo"« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(2, 4, 1)), 'Search for »"test foo"« returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch('foo', array('type,item'))->sort($this->getFieldId('id'), 'ASC')->execute();
    $this->assertEqual($results['result count'], 2, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2)), 'Search for »foo« returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results['result count'], 1, 'Complex search 1 returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(4)), 'Complex search 1 returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');
  }

  protected function checkFacets() {
    $query = $this->buildSearch();
    $filter = $query->createFilter('OR', array('facet:type'));
    $filter->condition($this->getFieldId('type'), 'article');
    $query->filter($filter);
    $facets['type'] = array(
      'field' => $this->getFieldId('type'),
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results['result count'], 2, 'OR facets query returned correct number of results.');
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 2, 'filter' => '"item"'),
      array('count' => 1, 'filter' => '!'),
    );
    // @todo Fix facets!
    //$facet_match = _search_api_settings_equals($results['search_api_facets']['type'], $expected);
    //$this->assertTrue($facet_match, 'Correct OR facets were returned');

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR', array('facet:' . $this->getFieldId('type')));
    $filter->condition($this->getFieldId('type'), 'article');
    $query->filter($filter);
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('type'), NULL, '<>');
    $query->filter($filter);
    $facets['type'] = array(
      'field' => $this->getFieldId('type'),
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results['result count'], 2, 'OR facets query returned correct number of results.');
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 2, 'filter' => '"item"'),
    );
    // @todo Fix facets!
    //$facet_match = _search_api_settings_equals($results['search_api_facets']['type'], $expected);
    //$this->assertTrue($facet_match, 'Correct OR facets were returned');
  }

  protected function editServer() {
    $server = entity_load('search_api_server', $this->serverId, TRUE);
    $server->backendPluginConfig['min_chars'] = 4;
    $success = (bool) $server->save();
    $this->assertTrue($success, 'The server was successfully edited.');

    $this->clearIndex();
    $this->indexItems();

    // Reset the internal cache so the new values will be available.
    entity_load('search_api_index', $this->indexId, TRUE);
  }

  protected function searchSuccess2() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEqual($results['result count'], 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(4, 1)), 'Search for »test« returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch(NULL, array('body,test foobar'))->execute();
    $this->assertEqual($results['result count'], 1, 'Search with multi-term fulltext filter returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'Search with multi-term fulltext filter returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch('"test foo"')->execute();
    $this->assertEqual($results['result count'], 4, 'Search for »"test foo"« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(2, 4, 1, 3)), 'Search for »"test foo"« returned correct result.');
    $this->assertEqual($results['ignored'], array('foo'), 'Short key was ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch('foo', array('type,item'))->execute();
    $this->assertEqual($results['result count'], 3, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 3)), 'Search for »foo« returned correct result.');
    $this->assertEqual($results['ignored'], array('foo'), 'Short key was ignored.');
    $this->assertEqual($results['warnings'], array(t('No valid search keys were present in the query.')), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results['result count'], 1, 'Complex search 1 returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'Complex search 1 returned correct result.');
    $this->assertEqual($results['ignored'], array('baz', 'bar'), 'Correct keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results['result count'], 1, 'Complex search 2 returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'Complex search 2 returned correct result.');
    $this->assertEqual($results['ignored'], array('baz', 'bar'), 'Correct keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch(NULL, array('keywords,orange'))->execute();
    $this->assertEqual($results['result count'], 3, 'Filter query 1 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 5)), 'Filter query 1 on multi-valued field returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'Warning displayed.');

    $filters = array(
      'keywords,orange',
      'keywords,apple',
    );
    $results = $this->buildSearch(NULL, $filters)->execute();
    $this->assertEqual($results['result count'], 1, 'Filter query 2 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(2)), 'Filter query 2 on multi-valued field returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $results = $this->buildSearch()->condition($this->getFieldId('keywords'), NULL)->execute();
    $this->assertEqual($results['result count'], 1, 'Query with NULL filter returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'Query with NULL filter returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');
  }

  /**
   * Executes regression tests for issues that were already fixed.
   */
  protected function regressionTests() {
    // Regression tests for #2007872.
    $results = $this->buildSearch('test')->sort($this->getFieldId('id'), 'ASC')->sort($this->getFieldId('type'), 'ASC')->execute();
    $this->assertEqual($results['result count'], 4, 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 3, 4)), 'Sorting on field with NULLs returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('id'), 3);
    $filter->condition($this->getFieldId('type'), 'article');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 3, 'OR filter on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3, 4, 5)), 'OR filter on field with NULLs returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    // Regression tests for #1863672.
    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 4, 'OR filter on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 4, 5)), 'OR filter on multi-valued field returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $query->filter($filter);
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 3, 'Multiple OR filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(2, 4, 5)), 'Multiple OR filters on multi-valued field returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $query = $this->buildSearch();
    $filter1 = $query->createFilter('OR');
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter1->filter($filter);
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $filter1->filter($filter);
    $query->filter($filter1);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 3, 'Complex nested filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(2, 4, 5)), 'Complex nested filters on multi-valued field returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    // Regression tests for #2040543.
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => $this->getFieldId('type'),
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 2, 'filter' => '"item"'),
      array('count' => 1, 'filter' => '!'),
    );
    // @todo Fix facets!
    //usort($results['search_api_facets']['type'], array($this, 'facetCompare'));
    //$this->assertEqual($results['search_api_facets']['type'], $expected, 'Correct facets were returned');

    $query = $this->buildSearch();
    $facets['type']['missing'] = FALSE;
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 2, 'filter' => '"item"'),
    );
    // @todo Fix facets!
    //usort($results['search_api_facets']['type'], array($this, 'facetCompare'));
    //$this->assertEqual($results['search_api_facets']['type'], $expected, 'Correct facets were returned');

    // Regression tests for #2111753.
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 3, 'OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 4)), 'OR keywords returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results['result count'], 5, 'Multi-field OR keywords returned correct number of results.');
    $this->assertTrue(empty($results['results']), 'Multi-field OR keywords returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 4, 'Nested OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 4, 5)), 'Nested OR keywords returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'OR',
      array(
        '#conjunction' => 'AND',
        'foo',
        'test',
      ),
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results['result count'], 4, 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 4, 5)), 'Nested multi-field OR keywords returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    // Regression tests for #2127001.
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results['result count'], 2, 'Negated AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3, 4)), 'Negated AND fulltext search returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results['result count'], 1, 'Negated OR fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'Negated OR fulltext search returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ),
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results['result count'], 2, 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3, 4)), 'Nested NOT AND fulltext search returned correct result.');
    $this->assertEqual($results['ignored'], array(), 'No keys were ignored.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    // Regression tests for #2136409
    // @todo Fix NULL and NOT NULL conditions.
//    $query = $this->buildSearch();
//    $query->condition($this->getFieldId('type'), NULL);
//    $query->sort($this->getFieldId('id'), 'ASC');
//    $results = $query->execute();
//    $this->assertEqual($results['result count'], 1, 'NULL filter returned correct number of results.');
//    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(3)), 'NULL filter returned correct result.');
//
//    $query = $this->buildSearch();
//    $query->condition($this->getFieldId('type'), NULL, '<>');
//    $query->sort($this->getFieldId('id'), 'ASC');
//    $results = $query->execute();
//    $this->assertEqual($results['result count'], 4, 'NOT NULL filter returned correct number of results.');
//    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(1, 2, 4, 5)), 'NOT NULL filter returned correct result.');

    // Regression tests for #1658964.
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => $this->getFieldId('type'),
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->condition($this->getFieldId('type'), 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 0, 'filter' => '!'),
      array('count' => 0, 'filter' => '"item"'),
    );
    usort($results['search_api_facets']['type'], array($this, 'facetCompare'));
    $this->assertEqual($results['search_api_facets']['type'], $expected, 'Correct facets were returned');
  }

  /**
   * Compares two facets for ordering.
   *
   * Used as a callback for usort() in regressionTests().
   */
  protected function facetCompare($a, $b) {
    if ($a['count'] != $b['count']) {
      return $b['count'] - $a['count'];
    }
    return strcasecmp($a['filter'], $b['filter']);
  }

  protected function clearIndex() {
    $index = entity_load('search_api_index', $this->indexId);
    $index->clear();
  }

  /**
   * Executes regression tests which are unpractical to run in between.
   */
  protected function regressionTests2() {
    // Create a 'keywords' field on the test entity type.
    entity_create('field_config', array(
      'name' => 'prices',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Prices',
    ))->save();

    // Regression test for #1916474.
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->options['fields'][$this->getFieldId('prices')]['type'] = 'decimal';
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    \Drupal::entityManager()->getStorage('search_api_server')->resetCache(array($this->serverId));
    \Drupal::entityManager()->getStorage('search_api_index')->resetCache(array($this->serverId));

    entity_create('entity_test', array(
      'id' => 6,
      'prices' => array('3.5', '3.25', '3.75', '3.5'),
      'type' => 'item',
    ))->save();

    $this->indexItems();

    $query = $this->buildSearch(NULL, array('prices,3.25'));
    $results = $query->execute();
    $this->assertEqual($results['result count'], 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');

    $query = $this->buildSearch(NULL, array('prices,3.5'));
    $results = $query->execute();
    $this->assertEqual($results['result count'], 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results['results']), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertEqual($results['warnings'], array(), 'No warnings were displayed.');
  }

  /**
   * Tests whether removing the configuration again works as it should.
   */
  protected function uninstallModule() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = entity_load('search_api_server', $this->serverId, TRUE);
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $server->deleteAllItems($index);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEqual($results['result count'], 0, 'Clearing the server worked correctly.');
    $table = 'search_api_db_' . $this->indexId;
    $this->assertTrue(db_table_exists($table), 'The index tables were left in place.');

    // Remove first the index and then the server.
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->serverMachineName = NULL;
    $index->save();

    $server = entity_load('search_api_server', $this->serverId, TRUE);
    $this->assertEqual($server->backendPluginConfig['field_tables'], array(), 'The index was successfully removed from the server.');
    $this->assertFalse(db_table_exists($table), 'The index tables were deleted.');
    $server->delete();

    // Uninstall the module.
    \Drupal::moduleHandler()->uninstall(array('search_api_db'), FALSE);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('search_api_db'), 'The Database Search module was successfully disabled.');
    $prefix = \Drupal::database()->prefixTables('{search_api_db_}') . '%';
    $this->assertEqual(\Drupal::database()->schema()->findTables($prefix), array(), 'The Database Search module was successfully uninstalled.');
  }

  /**
   * Returns the internal field ID for the given entity field name.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The internal field ID.
   */
  protected function getFieldId($field_name) {
    return 'entity:entity_test' . IndexInterface::DATASOURCE_ID_SEPARATOR . $field_name;
  }

  /**
   * Returns the idem IDs for the given entity IDs.
   *
   * @param array $entity_ids
   *   An array of entity IDs.
   *
   * @return array
   *   An array of item IDs.
   */
  protected function getItemIds(array $entity_ids) {
    return array_map(function ($entity_id) {
      return 'entity:entity_test' . IndexInterface::DATASOURCE_ID_SEPARATOR . $entity_id . ':' . Language::LANGCODE_NOT_SPECIFIED;
    }, $entity_ids);
  }

}
