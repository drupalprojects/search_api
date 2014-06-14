<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiRenderedItemProcessorTestCase.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\search_api\Tests\SearchApiProcessorTestBase;

class SearchApiRenderedItemProcessorTestCase extends SearchApiProcessorTestBase {

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
  public static $modules = array('user', 'node', 'search_api','search_api_db', 'search_api_test_backend', 'comment', 'entity_reference', 'system', 'routing');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests RenderedItem Processor Plugin',
      'description' => 'Tests if the processor adds correct rendered nodes as configured.',
      'group' => 'Search API',
    );
  }

  /**
   * Setup a minimalistic environment including a an RenderedItem Processor.
   */
  public function setUp() {
    parent::setUp('search_api_rendered_item');

    // Load configuration and needed schemas.
    $this->installConfig(array('system', 'filter'));
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
    $anonymous_session = new AnonymousUserSession();
    $anonymous_user = entity_create('user', array(
      'uid' => $anonymous_session->id(),
      'name' => $anonymous_session->getUsername(),
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
      if ($item['#datasource'] == 'entity:node') {
        $this->assertTrue(array_key_exists('search_api_rendered_item', $item), 'Node item ' . $idx . ' was rendered and stored in the item.');
        $this->assertEqual($item['search_api_rendered_item']['original_type'], 'string', 'Node item ' . $idx . ' rendered value is identified as a string.');

        $this->assertTrue(substr_count($item['search_api_rendered_item']['value'][0], 'view-mode-full') > 0, 'Node item ' . $idx . ' rendered in view-mode "full".');
        $this->assertTrue(substr_count($item['search_api_rendered_item']['value'][0], 'field-name-title') > 0, 'Node item ' . $idx . ' has a rendered title field.');
        $this->assertTrue(substr_count($item['search_api_rendered_item']['value'][0], '>' . $this->node_data['title'] . '<') > 0, 'Node item ' . $idx . ' has a rendered title inside HTML-Tags.');
        $this->assertTrue(substr_count($item['search_api_rendered_item']['value'][0], '>Member for<') > 0, 'Node item ' . $idx . ' has rendered member information HTML-Tags.');
        $this->assertTrue(substr_count($item['search_api_rendered_item']['value'][0], '>' . $this->node_data['body']['value'] . '<') > 0, 'Node item ' . $idx . ' has rendered content inside HTML-Tags.');
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
    $this->assertTrue(array_key_exists('search_api_rendered_item', $properties), 'The Properties where modified with the "search_api_rendered_item".');
    $this->assertTrue(($properties['search_api_rendered_item'] instanceof DataDefinition), 'The "search_api_rendered_item" contains a valid DataDefinition instance.');
    $this->assertEqual('string', $properties['search_api_rendered_item']->getDataType(), 'Valid DataType set in the DataDefinition.');
    $this->assertEqual('Rendered HTML output', $properties['search_api_rendered_item']->getLabel(), 'Valid Label set in the DataDefinition.');
    $this->assertEqual('The complete HTML which would be created when viewing the item.', $properties['search_api_rendered_item']->getDescription(), 'Valid Description set in the DataDefinition.');
  }

  /**
   * Testing the configuration form render array.
   */
  public function testBuildConfigurationForm() {
    $config = $this->processor->getConfiguration();
    $expected_form = array(
      'view_mode' => array(
        'entity:comment' => array(
          '#type' => 'value',
          '#value' => 'full',
         ),
        'entity:node' => array(
          '#type' => 'select',
          '#title' => 'View mode for data source Content',
          '#options' => array(
            'full' => 'Full content',
            'rss' => 'RSS',
            'search_index' => 'Search index',
            'search_result' => 'Search result highlighting input',
            'teaser' => 'Teaser',
           ),
          '#default_value' => 'full',
        ),
      ),
      'roles' => array(
        '#type' => 'select',
        '#title' => 'User roles',
        '#description' => 'The data will be processed as seen by a user with the selected roles.',
        '#options' => user_role_names(),
        '#multiple' => TRUE,
        '#default_value' => array('anonymous'),
        '#required' => TRUE,
      ),
    );

    $form_state = array();
    $form = $this->processor->buildConfigurationForm(array(), $form_state);
    $this->assertEqual($form, $expected_form, 'The configuration form has the expected structure.');
  }

}