<?php

namespace Drupal\Tests\search_api\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Tests\ExampleContentTrait;
use Drupal\search_api\Utility;

/**
 * Provides a base class for backend tests.
 */
abstract class BackendTestBase extends KernelTestBase {

  use ExampleContentTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array(
    'field',
    'search_api',
    'user',
    'system',
    'entity_test',
    'text',
    'search_api_test_example_content',
  );

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId = 'search_server';

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installSchema('system', array('router'));
    $this->installSchema('user', array('users_data'));
    $this->installEntitySchema('entity_test');
    $this->installConfig('search_api_test_example_content');

    // Do not use a batch for tracking the initial items after creating an
    // index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);

    $this->setUpExampleStructure();
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testFramework() {
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerBackend();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    $this->searchSuccess();
    $this->checkFacets();
    $this->checkSecondServer();
    $this->regressionTests();
    $this->clearIndex();

    $this->indexItems($this->indexId);
    $this->backendSpecificRegressionTests();
    $this->checkBackendSpecificFeatures();
    $this->clearIndex();

    $this->enableHtmlFilter();
    $this->indexItems($this->indexId);
    $this->disableHtmlFilter();
    $this->clearIndex();

    $this->searchNoResults();
    $this->regressionTests2();

    $this->checkModuleUninstall();
  }

  /**
   * Tests the correct setup of the server backend.
   */
  abstract protected function checkServerBackend();

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  abstract protected function updateIndex();

  /**
   * Tests that a second server doesn't interfere with the first.
   */
  abstract protected function checkSecondServer();

  /**
   * Tests whether removing the configuration again works as it should.
   */
  abstract protected function checkModuleUninstall();

  /**
   * Checks backend specific features.
   */
  protected function checkBackendSpecificFeatures() {
    $this->assertTrue(TRUE, 'There are no backend specific features to test.');
  }

  /**
   * Runs backend specific regression tests.
   */
  protected function backendSpecificRegressionTests() {
    $this->assertTrue(TRUE, 'There are no backend specific regression tests.');
  }

  /**
   * Tests the server that was installed through default configuration files.
   */
  protected function checkDefaultServer() {
    $server = $this->getServer();
    $this->assertTrue((bool) $server, 'The server was successfully created.');
  }

  /**
   * Tests the index that was installed through default configuration files.
   */
  protected function checkDefaultIndex() {
    $index = $this->getIndex();
    $this->assertTrue((bool) $index, 'The index was successfully created.');

    $this->assertEquals(array("entity:entity_test"), $index->getDatasourceIds(), 'Datasources are set correctly.');
    $this->assertEquals('default', $index->getTrackerId(), 'Tracker is set correctly.');

    $this->assertEquals(5, $index->getTrackerInstance()->getTotalItemsCount(), 'Correct item count.');
    $this->assertEquals(0, $index->getTrackerInstance()->getIndexedItemsCount(), 'All items still need to be indexed.');
  }

  /**
   * Enables the "HTML Filter" processor for the index.
   */
  protected function enableHtmlFilter() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();

    /** @var \Drupal\search_api\Processor\ProcessorInterface $processor */
    $processor = \Drupal::getContainer()
      ->get('plugin.manager.search_api.processor')
      ->createInstance('html_filter');

    $index->addProcessor($processor);
    $index->save();

    $this->assertArrayHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is added.');
  }

  /**
   * Disables the "HTML Filter" processor for the index.
   */
  protected function disableHtmlFilter() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    $index->removeField('body');
    $index->removeProcessor('html_filter');
    $index->save();

    $this->assertArrayNotHasKey('html_filter', $index->getProcessors(), 'HTML filter processor is removed.');
    $this->assertArrayNotHasKey('body', $index->getFields(), 'Body field is removed.');
  }

  /**
   * Builds a search query for testing purposes.
   *
   * Used as a helper method during testing.
   *
   * @param string|array|null $keys
   *   (optional) The search keys to set, if any.
   * @param string[] $conditions
   *   (optional) Conditions to set on the query, in the format "field,value".
   * @param string[]|null $fields
   *   (optional) Fulltext fields to search for the keys.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query on the test index.
   */
  protected function buildSearch($keys = NULL, array $conditions = array(), array $fields = NULL) {
    $query = $this->getIndex()->query();
    if ($keys) {
      $query->keys($keys);
      if ($fields) {
        $query->setFulltextFields($fields);
      }
    }
    foreach ($conditions as $condition) {
      list($field, $value) = explode(',', $condition, 2);
      $query->addCondition($field, $value);
    }
    $query->range(0, 10);

    return $query;
  }

  /**
   * Tests that a search on the index doesn't have any results.
   */
  protected function searchNoResults() {
    $results = $this->buildSearch('test')->execute();
    $this->assertEquals(0, $results->getResultCount(), 'No search results returned without indexing.');
    $this->assertEquals(array(), array_keys($results->getResultItems()), 'No search results returned without indexing.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess() {
    $results = $this->buildSearch('test')->range(1, 2)->sort('id', QueryInterface::SORT_ASC)->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search for »test« returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 3)), array_keys($results->getResultItems()), 'Search for »test« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $ids = $this->getItemIds(array(2));
    $id = reset($ids);
    $this->assertEquals($id, key($results->getResultItems()));
    $this->assertEquals($id, $results->getResultItems()[$id]->getId());
    $this->assertEquals('entity:entity_test', $results->getResultItems()[$id]->getDatasourceId());

    $results = $this->buildSearch('test foo')->sort('id', QueryInterface::SORT_ASC)->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Search for »test foo« returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4)), array_keys($results->getResultItems()), 'Search for »test foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch('foo', array('type,item'))->sort('id', QueryInterface::SORT_ASC)->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Search for »foo« returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2)), array_keys($results->getResultItems()), 'Search for »foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

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
    $this->assertEquals(1, $results->getResultCount(), 'Complex search 1 returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4)), array_keys($results->getResultItems()), 'Complex search 1 returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch()->sort('id');
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('name', 'bar');
    $conditions->addCondition('body', 'bar');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Search with multi-field fulltext filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 3, 5)), array_keys($results->getResultItems()), 'Search with multi-field fulltext filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()->addCondition('keywords', array('grape', 'apple'), 'IN')->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Query with IN filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Query with IN filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()->addCondition('keywords', array('grape', 'apple'), 'NOT IN')->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Query with NOT IN filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 3)), array_keys($results->getResultItems()), 'Query with NOT IN filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()->addCondition('width', array('0.9', '1.5'), 'BETWEEN')->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Query with BETWEEN filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4)), array_keys($results->getResultItems()), 'Query with BETWEEN filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()
      ->addCondition('width', array('0.9', '1.5'), 'NOT BETWEEN')
      ->sort('id')
      ->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Query with NOT BETWEEN filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 3, 5)), array_keys($results->getResultItems()), 'Query with NOT BETWEEN filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()
      ->setLanguages(array('und', 'en'))
      ->addCondition('keywords', array('grape', 'apple'), 'IN')
      ->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Query with IN filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Query with IN filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR')
      ->addCondition('search_api_language', 'und')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN');
    $query->addConditionGroup($conditions);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Query with search_api_language filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4)), array_keys($results->getResultItems()), 'Query with search_api_language filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()
      ->addCondition('search_api_language', array('und', 'en'), 'IN')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN')
      ->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Query with search_api_language "IN" filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4)), array_keys($results->getResultItems()), 'Query with search_api_language filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()
      ->addCondition('search_api_language', array('und', 'de'), 'NOT IN')
      ->addCondition('width', array('0.9', '1.5'), 'BETWEEN')
      ->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Query with search_api_language "NOT IN" filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4)), array_keys($results->getResultItems()), 'Query with search_api_language filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Tests whether facets work correctly.
   */
  protected function checkFacets() {
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', array('facet:' . 'category'));
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'OR facets query returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4, 5)), array_keys($results->getResultItems()));
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
      array('count' => 1, 'filter' => '!'),
    );
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $category_facets, 'Correct OR facets were returned');

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR', array('facet:' . 'category'));
    $conditions->addCondition('category', 'article_category');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('category', NULL, '<>');
    $query->addConditionGroup($conditions);
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
      'operator' => 'or',
    );
    $query->setOption('search_api_facets', $facets);
    $results = $query->execute();
    $this->assertEquals(2, $results->getResultCount(), 'OR facets query returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(4, 5)), array_keys($results->getResultItems()));
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
    );
    $category_facets = $results->getExtraData('search_api_facets')['category'];
    usort($category_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $category_facets, 'Correct OR facets were returned');
  }

  /**
   * Executes regression tests for issues that were already fixed.
   */
  protected function regressionTests() {
    $this->regressionTest2007872();
    $this->regressionTest1863672();
    $this->regressionTest2040543();
    $this->regressionTest2111753();
    $this->regressionTest2127001();
    $this->regressionTest2136409();
    $this->regressionTest1658964();
    $this->regressionTest2469547();
    $this->regressionTest1403916();
  }

  /**
   * Regression tests for missing results when using OR filters.
   *
   * @see https://www.drupal.org/node/2007872
   */
  protected function regressionTest2007872() {
    $results = $this->buildSearch('test')
      ->sort('id', QueryInterface::SORT_ASC)
      ->sort('type', QueryInterface::SORT_ASC)
      ->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 3, 4)), array_keys($results->getResultItems()), 'Sorting on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('id', 3);
    $conditions->addCondition('type', 'article');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'OR filter on field with NULLs returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4, 5)), array_keys($results->getResultItems()), 'OR filter on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Regression tests for same content multiple times in the search result.
   *
   * Error was caused by multiple terms for filter.
   *
   * @see https://www.drupal.org/node/1863672
   */
  protected function regressionTest1863672() {
    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'OR filter on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'OR filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'strawberry');
    $query->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('OR');
    $conditions->addCondition('keywords', 'apple');
    $conditions->addCondition('keywords', 'grape');
    $query->addConditionGroup($conditions);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Multiple OR filters on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Multiple OR filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $conditions1 = $query->createConditionGroup('OR');
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'orange');
    $conditions->addCondition('keywords', 'apple');
    $conditions1->addConditionGroup($conditions);
    $conditions = $query->createConditionGroup('AND');
    $conditions->addCondition('keywords', 'strawberry');
    $conditions->addCondition('keywords', 'grape');
    $conditions1->addConditionGroup($conditions);
    $query->addConditionGroup($conditions1);
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'Complex nested filters on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(2, 4, 5)), array_keys($results->getResultItems()), 'Complex nested filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Regression tests for (none) facet shown when feature is set to "no".
   *
   * @see https://www.drupal.org/node/2040543
   */
  protected function regressionTest2040543() {
    $query = $this->buildSearch();
    $facets['category'] = array(
      'field' => 'category',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
      array('count' => 1, 'filter' => '!'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');

    $query = $this->buildSearch();
    $facets['category']['missing'] = FALSE;
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article_category"'),
      array('count' => 2, 'filter' => '"item_category"'),
    );
    $type_facets = $results->getExtraData('search_api_facets')['category'];
    usort($type_facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $type_facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for searching for multiple words using "OR" condition.
   *
   * @see https://www.drupal.org/node/2111753
   */
  protected function regressionTest2111753() {
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array('name'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(3, $results->getResultCount(), 'OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4)), array_keys($results->getResultItems()), 'OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'Multi-field OR keywords returned correct number of results.');
    $this->assertFalse($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

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
    $query = $this->buildSearch($keys, array(), array('name'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Nested OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'Nested OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

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
    $query = $this->buildSearch($keys, array(), array('name', 'body'));
    $query->sort('id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'Nested multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Regression tests for non-working operator "contains none of these words".
   *
   * @see https://www.drupal.org/node/2127001
   */
  protected function regressionTest2127001() {
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', QueryInterface::SORT_ASC)->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Negated AND fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4)), array_keys($results->getResultItems()), 'Negated AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Negated OR fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3)), array_keys($results->getResultItems()), 'Negated OR fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

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
    $results = $this->buildSearch($keys)->sort('search_api_id', QueryInterface::SORT_ASC)->execute();
    $this->assertEquals(2, $results->getResultCount(), 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3, 4)), array_keys($results->getResultItems()), 'Nested NOT AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Regression tests for handling of NULL filters.
   *
   * @see https://www.drupal.org/node/2136409
   */
  protected function regressionTest2136409() {
    $query = $this->buildSearch();
    $query->addCondition('category', NULL);
    $query->sort('search_api_id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'NULL filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(3)), array_keys($results->getResultItems()), 'NULL filter returned correct result.');

    $query = $this->buildSearch();
    $query->addCondition('category', NULL, '<>');
    $query->sort('search_api_id', QueryInterface::SORT_ASC);
    $results = $query->execute();
    $this->assertEquals(4, $results->getResultCount(), 'NOT NULL filter returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 4, 5)), array_keys($results->getResultItems()), 'NOT NULL filter returned correct result.');
  }

  /**
   * Regression tests for facets with counts of 0.
   *
   * @see https://www.drupal.org/node/1658964
   */
  protected function regressionTest1658964() {
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('type', 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 0, 'filter' => '!'),
      array('count' => 0, 'filter' => '"item"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Regression tests for facets on fulltext fields.
   *
   * @see https://www.drupal.org/node/2469547
   */
  protected function regressionTest2469547() {
    $query = $this->buildSearch();
    $facets = array();
    $facets['body'] = array(
      'field' => 'body',
      'limit' => 0,
      'min_count' => 1,
      'missing' => FALSE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->addCondition('id', 5, '<>');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 4, 'filter' => '"test"'),
      array('count' => 2, 'filter' => '"Case"'),
      array('count' => 2, 'filter' => '"casE"'),
      array('count' => 1, 'filter' => '"bar"'),
      array('count' => 1, 'filter' => '"case"'),
      array('count' => 1, 'filter' => '"foobar"'),
    );
    // We can't guarantee the order of returned facets, since "bar" and "foobar"
    // both occur once, so we have to manually sort the returned facets first.
    $facets = $results->getExtraData('search_api_facets', array())['body'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned for a fulltext field.');
  }

  /**
   * Regression tests for multi word search results sets and wrong facet counts.
   *
   * @see https://www.drupal.org/node/1403916
   */
  protected function regressionTest1403916() {
    $query = $this->buildSearch('test foo');
    $facets = array();
    $facets['type'] = array(
      'field' => 'type',
      'limit' => 0,
      'min_count' => 1,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"item"'),
      array('count' => 1, 'filter' => '"article"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEquals($expected, $facets, 'Correct facets were returned');
  }

  /**
   * Compares two facet filters to determine their order.
   *
   * Used as a callback for usort() in regressionTests().
   *
   * Will first compare the counts, ranking facets with higher count first, and
   * then by filter value.
   *
   * @param array $a
   *   The first facet filter.
   * @param array $b
   *   The second facet filter.
   *
   * @return int
   *   -1 or 1 if the first filter should, respectively, come before or after
   *   the second; 0 if both facet filters are equal.
   */
  protected function facetCompare(array $a, array $b) {
    if ($a['count'] != $b['count']) {
      return $b['count'] - $a['count'];
    }
    return strcmp($a['filter'], $b['filter']);
  }

  /**
   * Clears the test index.
   */
  protected function clearIndex() {
    $this->getIndex()->clear();
  }

  /**
   * Executes regression tests which are unpractical to run in between.
   */
  protected function regressionTests2() {
    // Create a "prices" field on the test entity type.
    FieldStorageConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    FieldConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Prices',
    ))->save();

    $this->regressionTest1916474();
    $this->regressionTest2284199();
    $this->regressionTest2471509();
  }

  /**
   * Regression tests for correctly indexing  multiple float/decimal fields.
   *
   * @see https://www.drupal.org/node/1916474
   */
  protected function regressionTest1916474() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    $this->addField($index, 'prices', 'decimal');
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    $this->resetEntityCache('server');
    $this->resetEntityCache();

    $this->addTestEntity(6, array(
      'prices' => array('3.5', '3.25', '3.75', '3.5'),
      'type' => 'item',
    ));

    $this->indexItems($this->indexId);

    $query = $this->buildSearch(NULL, array('prices,3.25'));
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Filter on decimal field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(6)), array_keys($results->getResultItems()), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch(NULL, array('prices,3.5'));
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Filter on decimal field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(6)), array_keys($results->getResultItems()), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Use the "prices" field, since we've added it now, to also check for
    // proper handling of (NOT) BETWEEN for multi-valued fields.
    $query = $this->buildSearch()
      ->addCondition('prices', array(3.6, 3.8), 'BETWEEN');
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'BETWEEN filter on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(6)), array_keys($results->getResultItems()), 'BETWEEN filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch()
      ->addCondition('prices', array(3.6, 3.8), 'NOT BETWEEN');
    $results = $query->execute();
    $this->assertEquals(5, $results->getResultCount(), 'NOT BETWEEN filter on multi-valued field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(1, 2, 3, 4, 5)), array_keys($results->getResultItems()), 'NOT BETWEEN filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Regression tests for problems with taxonomy term parent.
   *
   * @see https://www.drupal.org/node/2284199
   */
  protected function regressionTest2284199() {
    $this->addTestEntity(7, array('type' => 'item'));

    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with an empty value for a non string field worked.');
  }

  /**
   * Regression tests for strings longer than 50 chars.
   *
   * @see https://www.drupal.org/node/2471509
   * @see https://www.drupal.org/node/2616268
   */
  protected function regressionTest2471509() {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    $this->addField($index, 'body');
    $index->save();
    $this->indexItems($this->indexId);

    $this->addTestEntity(8, array(
      'name' => 'Article with long body',
      'type' => 'article',
      'body' => 'astringlongerthanfiftycharactersthatcantbestoredbythedbbackend',
    ));
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(1, $count, 'Indexing an item with a word longer than 50 characters worked.');

    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $count = $this->indexItems($this->indexId);
    $this->assertEquals(count($this->entities), $count, 'Switching type from text to string worked.');

    // For a string field, 50 characters shouldn't be a problem.
    $query = $this->buildSearch(NULL, array('body,astringlongerthanfiftycharactersthatcantbestoredbythedbbackend'));
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Filter on new string field returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(8)), array_keys($results->getResultItems()), 'Filter on new string field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $index->removeField('body');
    $index->save();
  }

  /**
   * Regression tests for multibyte characters exceeding 50 byte.
   *
   * @see https://www.drupal.org/node/2616804
   */
  protected function regressionTests2616804() {
    // The word has 28 Unicode characters but 56 bytes. Verify that it is still
    // indexed correctly.
    $mb_word = 'äöüßáŧæøðđŋħĸµäöüßáŧæøðđŋħĸµ';
    // We put the word 8 times into the body so we can also verify that the 255
    // character limit for strings counts characters, not bytes.
    $mb_body = implode(' ', array_fill(0, 8, $mb_word));
    $this->addTestEntity(9, array(
      'name' => 'Test item 9',
      'type' => 'item',
      'body' => $mb_body,
    ));
    $entity_count = count($this->entities);
    $count = $this->indexItems($this->indexId);
    $this->assertEquals($entity_count, $count, 'Indexing an item with a word with 28 multi-byte characters worked.');

    $query = $this->buildSearch($mb_word);
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for word with 28 multi-byte characters returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(9)), array_keys($results->getResultItems()), 'Search for word with 28 multi-byte characters returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch($mb_word . 'ä');
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Search for unknown word with 29 multi-byte characters returned no results.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Test the same body when indexed as a string (255 characters limit should
    // not be reached).
    $index = $this->getIndex();
    $index->getField('body')->setType('string');
    $index->save();
    $count = $index->indexItems();
    $this->assertEquals($entity_count, $count, 'Switching type from text to string worked.');

    $query = $this->buildSearch(NULL, array("body,$mb_body"));
    $results = $query->execute();
    $this->assertEquals(1, $results->getResultCount(), 'Search for body with 231 multi-byte characters returned correct number of results.');
    $this->assertEquals($this->getItemIds(array(9)), array_keys($results->getResultItems()), 'Search for body with 231 multi-byte characters returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch(NULL, array("body,{$mb_body}ä"));
    $results = $query->execute();
    $this->assertEquals(0, $results->getResultCount(), 'Search for unknown body with 232 multi-byte characters returned no results.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $index->getField('body')->setType('text');
    $index->save();
  }

  /**
   * Asserts ignored fields from a set of search results.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The results to check.
   * @param array $ignored
   *   (optional) The ignored keywords that should be present, if any.
   * @param string $message
   *   (optional) The message to be displayed with the assertion.
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
    $this->assertEquals($ignored, $results->getIgnoredSearchKeys(), $message);
  }

  /**
   * Asserts warnings from a set of search results.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The results to check.
   * @param array $warnings
   *   (optional) The ignored warnings that should be present, if any.
   * @param string $message
   *   (optional) The message to be displayed with the assertion.
   */
  protected function assertWarnings(ResultSetInterface $results, array $warnings = array(), $message = 'No warnings were displayed.') {
    $this->assertEquals($warnings, $results->getWarnings(), $message);
  }

  /**
   * Retrieves the search server used by this test.
   *
   * @return \Drupal\search_api\ServerInterface
   *   The search server.
   */
  protected function getServer() {
    return Server::load($this->serverId);
  }

  /**
   * Retrieves the search index used by this test.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search index.
   */
  protected function getIndex() {
    return Index::load($this->indexId);
  }

  /**
   * Adds a field to a search index.
   *
   * The index will not be saved automatically.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string $property_name
   *   The property's name.
   * @param string $type
   *   (optional) The field type.
   */
  protected function addField(IndexInterface $index, $property_name, $type = 'text') {
    $field_info = array(
      'label' => $property_name,
      'type' => $type,
      'datasource_id' => 'entity:entity_test',
      'property_path' => $property_name,
    );
    $field = Utility::createField($index, $property_name, $field_info);
    $index->addField($field);
    $index->save();
  }

  /**
   * Resets the entity cache for the specified entity.
   *
   * @param string $type
   *   (optional) The type of entity whose cache should be reset. Either "index"
   *   or "server".
   */
  protected function resetEntityCache($type = 'index') {
    $entity_type_id = 'search_api_' . $type;
    \Drupal::entityTypeManager()
      ->getStorage($entity_type_id)
      ->resetCache(array($this->{$type . 'Id'}));
  }

}
