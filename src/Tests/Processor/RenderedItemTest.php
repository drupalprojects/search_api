<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\Processor\RenderedItemTest.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\Core\TypedData\DataDefinition;

/**
 * Tests the "Rendered item" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\RenderedItem
 */
class RenderedItemTest extends ProcessorTestBase {

  /**
   * Data for all nodes which are published.
   *
   * @var array
   */
  protected $node_data;

  /**
   * List of nodes which are published.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  public static $modules = array('user', 'node', 'menu_link', 'search_api','search_api_db', 'search_api_test_backend', 'comment', 'entity_reference', 'system', 'routing');

  /**
   * Setup a minimalistic environment including a an RenderedItem Processor.
   */
  public function setUp() {
    parent::setUp('rendered_item');

    // Load configuration and needed schemas.
    $this->installConfig(array('system', 'filter', 'node', 'comment'));
    $this->installSchema('system', array('router'));
    \Drupal::service('router.builder')->rebuild();

    // Create a node type for testing.
    $type = entity_create('node_type', array('type' => 'page', 'name' => 'page'));
    $type->save();

    // Create anonymous user name.
    $role = entity_create('user_role', array(
      'id' => 'anonymous',
      'label' => 'anonymous',
    ));
    $role->save();

    // Insert anonymous user into the database.
    $anonymous_user = entity_create('user', array(
      'uid' => 0,
      'name' => '',
    ));
    $anonymous_user->save();

    // Default node values for all nodes we create below.
    $this->node_data = array(
      'status' => NODE_PUBLISHED,
      'type' => 'page',
      'title' => $this->randomName(8),
      'body' => array('value' => $this->randomName(32), 'summary' => $this->randomName(16), 'format' => 'plain_text'),
      'uid' => $anonymous_user->id(),
    );

    // Create some test nodes with valid user on it for rendering a picture.
    $this->nodes[0] = entity_create('node', $this->node_data);
    $this->nodes[0]->save();
    $this->nodes[1] = entity_create('node', $this->node_data);
    $this->nodes[1]->save();

    // Configuration.
    $config = $this->processor->getConfiguration();
    $config['view_mode'] = array(
      'entity:node' => 'full',
      'entity:user' => 'compact',
      'entity:comment' => 'teaser',
    );
    $config['roles'] = array($role->id());
    $this->processor->setConfiguration($config);

    // Enable the processor field on the index.
    $fields = $this->index->getOption('fields');
    $fields['rendered_item'] = array(
      'type' => 'string',
    );
    $this->index->setOption('fields', $fields);
    $this->index->save();

    $this->index->getDatasources();
  }

  /**
   * Tests the item preprocessor.
   */
  public function testPreprocessIndexItems() {
    $items = array();
    foreach ($this->nodes as $node) {
      $items[] = array(
        'datasource' => 'entity:node',
        'item' => $node,
        'item_id' => $node->id(),
        'text' => $this->randomName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);
    foreach ($items as $key => $item) {
      $idx = substr($key, strrpos($key, '|') + 1);
      if ($item->getDatasourceId() == 'entity:node') {
        $field = $item->getField('rendered_item');
        $this->assertEqual($field->getType(), 'string', 'Node item ' . $idx . ' rendered value is identified as a string.');
        $values = $field->getvalues();
        // These tests rely on the template not changing. However there is
        // value for the specifics. For example the title text was present
        // when broken, because the schema metadata was also adding it to
        // the output.
        $this->assertTrue(substr_count($values[0], 'view-mode-full') > 0, 'Node item ' . $idx . ' rendered in view-mode "full".');
        $this->assertTrue(substr_count($values[0], 'field-name-title') > 0, 'Node item ' . $idx . ' has a rendered title field.');
        $this->assertTrue(substr_count($values[0], '>' . $this->node_data['title'] . '<') > 0, 'Node item ' . $idx . ' has a rendered title inside HTML-Tags.');
        $this->assertTrue(substr_count($values[0], '>Member for<') > 0, 'Node item ' . $idx . ' has rendered member information HTML-Tags.');
        $this->assertTrue(substr_count($values[0], '>' . $this->node_data['body']['value'] . '<') > 0, 'Node item ' . $idx . ' has rendered content inside HTML-Tags.');
      }
      else {
        $this->assert(FALSE, 'The processed item ' . $idx . ' has an unknown type: ' . $item['#datasource']);
      }
    }
  }

  /**
   * Tests alterPropertyDefinitions.
   *
   * Checks for the correct DataDefinition added to the properties.
   */
  public function testAlterPropertyDefinitions() {
    $properties = array();

    // Check for modified properties when no DataSource is given.
    $this->processor->alterPropertyDefinitions($properties, NULL);
    $this->assertTrue(array_key_exists('rendered_item', $properties), 'The Properties where modified with the "rendered_item".');
    $this->assertTrue(($properties['rendered_item'] instanceof DataDefinition), 'The "rendered_item" contains a valid DataDefinition instance.');
    $this->assertEqual('string', $properties['rendered_item']->getDataType(), 'Valid DataType set in the DataDefinition.');
    $this->assertEqual('Rendered HTML output', $properties['rendered_item']->getLabel(), 'Valid Label set in the DataDefinition.');
    $this->assertEqual('The complete HTML which would be created when viewing the item.', $properties['rendered_item']->getDescription(), 'Valid Description set in the DataDefinition.');
  }
}
