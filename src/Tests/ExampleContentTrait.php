<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\ExampleDataTrait.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\search_api\Index\IndexInterface;

/**
 * Contains helpers to create data that can be used by tests.
 */
trait ExampleContentTrait {

  protected $entities = array();

  /**
   * Sets up the necessary bundles and fields.
   */
  protected function setUpExampleStructure() {
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

  protected function insertExampleContent() {
    $count = \Drupal::entityQuery('entity_test')->count()->execute();

    $this->entities[1] = entity_create('entity_test', array(
        'id' => 1,
        'name' => 'foo bar baz',
        'body' => 'test test',
        'type' => 'item',
        'keywords' => array('orange'),
      ));
    $this->entities[1]->save();
    $this->entities[2] = entity_create('entity_test', array(
        'id' => 2,
        'name' => 'foo test',
        'body' => 'bar test',
        'type' => 'item',
        'keywords' => array('orange', 'apple', 'grape'),
      ));
    $this->entities[2]->save();
    $this->entities[3] = entity_create('entity_test', array(
        'id' => 3,
        'name' => 'bar',
        'body' => 'test foobar',
        'type' => 'item',
      ));
    $this->entities[3]->save();
    $this->entities[4] = entity_create('entity_test', array(
        'id' => 4,
        'name' => 'foo baz',
        'body' => 'test test test',
        'type' => 'article',
        'keywords' => array('apple', 'strawberry', 'grape'),
      ));
    $this->entities[4]->save();
    $this->entities[5] = entity_create('entity_test', array(
        'id' => 5,
        'name' => 'bar baz',
        'body' => 'foo',
        'type' => 'article',
        'keywords' => array('orange', 'strawberry', 'grape', 'banana'),
      ));
    $this->entities[5]->save();
    $count = \Drupal::entityQuery('entity_test')->count()->execute() - $count;
    $this->assertEqual($count, 5, "$count items inserted.");
  }

  protected function indexItems($index_id) {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $index_id);
    $index->index();
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
