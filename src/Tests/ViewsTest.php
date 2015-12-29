<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\ViewsTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Html;
use Drupal\search_api\Entity\Index;

/**
 * Tests the Views integration of the Search API.
 *
 * @group search_api
 */
class ViewsTest extends WebTestBase {

  use ExampleContentTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array('search_api_test_views');

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'database_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpExampleStructure();
    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->insertExampleContent();
    $this->indexItems($this->indexId);
  }

  /**
   * Tests a view with exposed filters.
   */
  public function testView() {
    $this->checkResults(array(), array_keys($this->entities), 'Unfiltered search');

    $this->checkResults(array('search_api_fulltext' => 'foobar'), array(3), 'Search for a single word');
    $this->checkResults(array('search_api_fulltext' => 'foo test'), array(1, 2, 4), 'Search for multiple words');
    $query = array(
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'or',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'OR search for multiple words');
    $query = array(
      'search_api_fulltext' => 'foobar',
      'search_api_fulltext_op' => 'not',
    );
    $this->checkResults($query, array(1, 2, 4, 5), 'Negated search');
    $query = array(
      'search_api_fulltext' => 'foo test',
      'search_api_fulltext_op' => 'not',
    );
    $this->checkResults($query, array(), 'Negated search for multiple words');
    $query = array(
      'search_api_fulltext' => 'fo',
    );
    $label = 'Search for short word';
    $this->checkResults($query, array(), $label);
    $this->assertText('You must include at least one positive keyword with 3 characters or more', "$label displayed the correct warning.");
    $query = array(
      'search_api_fulltext' => 'foo to test',
    );
    $label = 'Fulltext search including short word';
    $this->checkResults($query, array(1, 2, 4), $label);
    $this->assertNoText('You must include at least one positive keyword with 3 characters or more', "$label didn't display a warning.");

    $this->checkResults(array('id[value]' => 2), array(2), 'Search with ID filter');
    // @todo Enable "between" again. See #2624870.
//    $query = array(
//      'id[min]' => 2,
//      'id[max]' => 4,
//      'id_op' => 'between',
//    );
//    $this->checkResults($query, array(2, 3, 4), 'Search with ID "in between" filter');
    $query = array(
      'id[value]' => 2,
      'id_op' => '>',
    );
    $this->checkResults($query, array(3, 4, 5), 'Search with ID "greater than" filter');
    $query = array(
      'id[value]' => 2,
      'id_op' => '!=',
    );
    $this->checkResults($query, array(1, 3, 4, 5), 'Search with ID "not equal" filter');
    $query = array(
      'id_op' => 'empty',
    );
    $this->checkResults($query, array(), 'Search with ID "empty" filter');
    $query = array(
      'id_op' => 'not empty',
    );
    $this->checkResults($query, array(1, 2, 3, 4, 5), 'Search with ID "not empty" filter');

    $this->checkResults(array('keywords[value]' => 'apple'), array(2, 4), 'Search with Keywords filter');
    // @todo Enable "between" again. See #2624870.
//    $query = array(
//      'keywords[min]' => 'aardvark',
//      'keywords[max]' => 'calypso',
//      'keywords_op' => 'between',
//    );
//    $this->checkResults($query, array(2, 4, 5), 'Search with Keywords "in between" filter');
    $query = array(
      'keywords[value]' => 'radish',
      'keywords_op' => '>=',
    );
    $this->checkResults($query, array(1, 4, 5), 'Search with Keywords "greater than or equal" filter');
    $query = array(
      'keywords[value]' => 'orange',
      'keywords_op' => '!=',
    );
    $this->checkResults($query, array(3, 4), 'Search with Keywords "not equal" filter');
    $query = array(
      'keywords_op' => 'empty',
    );
    $this->checkResults($query, array(3), 'Search with Keywords "empty" filter');
    $query = array(
      'keywords_op' => 'not empty',
    );
    $this->checkResults($query, array(1, 2, 4, 5), 'Search with Keywords "not empty" filter');

    $query = array(
      'search_api_fulltext' => 'foo to test',
      'id[value]' => 2,
      'id_op' => '>',
      'keywords_op' => 'not empty',
    );
    $this->checkResults($query, array(4), 'Search with multiple filters');
  }

  /**
   * Checks the Views results for a certain set of parameters.
   *
   * @param array $query
   *   The GET parameters to set for the view.
   * @param int[]|null $expected_results
   *   (optional) The IDs of the expected results; or NULL to skip checking the
   *   results.
   * @param string $label
   *   (optional) A label for this search, to include in assert messages.
   */
  protected function checkResults(array $query, array $expected_results = NULL, $label = 'Search') {
    $this->drupalGet('search-api-test-fulltext', array('query' => $query));

    if (isset($expected_results)) {
      $count = count($expected_results);
      $count_assert_message = "$label returned correct number of results.";
      if ($count) {
        $this->assertText("Displaying $count search results", $count_assert_message);
      }
      else {
        $this->assertNoText('search results', $count_assert_message);
      }

      $expected_results = array_combine($expected_results, $expected_results);
      $actual_results = array();
      foreach ($this->entities as $id => $entity) {
        $entity_label = Html::escape($entity->label());
        if (strpos($this->getRawContent(), ">$entity_label<") !== FALSE) {
          $actual_results[$id] = $id;
        }
      }
      $this->assertEqual($expected_results, $actual_results, "$label returned correct results.");
    }
  }

}
