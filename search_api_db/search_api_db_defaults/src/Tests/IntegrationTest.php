<?php

/**
 * @file
 * Contains \Drupal\search_api_db_defaults\Tests\IntegrationTest.
 */

namespace Drupal\search_api_db_defaults\Tests;

use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the correct installation of the default configs.
 *
 * @group search_api
 */
class IntegrationTest extends WebTestBase {

  use StringTranslationTrait, CommentTestTrait, EntityReferenceTestTrait;

  /**
   * Modules to enable for this test.
   *
   * This module itself cannot be included in this list, since we need to set up
   * the right content type configuration before that.
   *
   * @var string[]
   */
  public static $modules = array('node', 'comment', 'field', 'image', 'taxonomy');

  /**
   * A non-admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $authenticatedUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create user with content access permission to see if the view is
    // accessible.
    $this->authenticatedUser = $this->drupalCreateUser();

    // Create "Article" and "Basic page" node bundles.
    $this->createNodeBundles();

    try {
      // Install the module and also auto-enable the dependencies.
      $module = 'search_api_db_defaults';
      $dependencies = $this->getModuleDependencies($module);
      $success = $this->container->get('module_installer')->install(array($module), TRUE);
      // Required after enabling a module using the module_installer service.
      $this->rebuildContainer();

      // Assert if the module was successfully enabled.
      $this->assertTrue($success, new FormattableMarkup('Enabled search_api_db_defaults, including its dependencies: %modules', array('%modules' => implode(', ', $dependencies))));
    }
    catch (UnmetDependenciesException $e) {
      // The exception message has all the details.
      $this->fail($e->getMessage());
    }

    // Rebuild menu so our anonymous user can access the search view.
    $route_builder = $this->container->get('router.builder');
    $route_builder->rebuild();

  }

  /**
   * Creates the necessary node bundles for the default configuration.
   */
  protected function createNodeBundles() {
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }
    // Add comments to article.
    $comment_type = CommentType::create(array(
      'id' => 'comment',
      'target_entity_type_id' => 'node',
    ));
    $comment_type->save();
    $this->addDefaultCommentField('node', 'article');

    // Add Image field to article.
    $field_name = strtolower('field_image');
    $min_resolution = 50;
    $max_resolution = 100;
    $field_settings = array(
      'max_resolution' => $max_resolution . 'x' . $max_resolution,
      'min_resolution' => $min_resolution . 'x' . $min_resolution,
      'alt_field' => 0,
    );
    $this->createImageField($field_name, 'article', array(), $field_settings);

    // Add tags field to Article.
    // Create a tags vocabulary for the 'article' content type.
    $vocabulary = \Drupal::entityManager()
      ->getStorage('taxonomy_vocabulary')
      ->create(array(
        'name' => 'Tags',
        'vid' => 'tags',
      ));
    $vocabulary->save();
    $field_name = 'field_' . $vocabulary->id();

    $handler_settings = array(
      'target_bundles' => array(
        $vocabulary->id() => $vocabulary->id(),
      ),
      'auto_create' => TRUE,
    );
    $this->createEntityReferenceField('node', 'article', $field_name, 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

   $this->getEntityDisplay('form', 'node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => -4,
      ))
      ->save();

   $this->getEntityDisplay('view', 'node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'entity_reference_label',
        'weight' => 10,
      ))
      ->save();
   $this->getEntityDisplay('view', 'node', 'article', 'teaser')
      ->setComponent($field_name, array(
        'type' => 'entity_reference_label',
        'weight' => 10,
      ))
      ->save();
  }

  /**
   * Lists all dependencies of the given module.
   *
   * @param string $module
   *   The module for which to check.
   *
   * @return string[]
   *   A numerically indexed array containing all dependencies of the module.
   */
  protected function getModuleDependencies($module) {
    // Get all module data so we can find dependencies and sort.
    $extension_config = \Drupal::configFactory()->getEditable('core.extension');
    $module_data = system_rebuild_module_data();
    $installed_modules = $extension_config->get('module') ?: array();
    $dependencies = array();
    // Add dependencies to the list. The new modules will be processed as
    // the while loop continues.
    foreach (array_keys($module_data[$module]->requires) as $dependency) {
      // Skip already installed modules.
      if (!isset($module_list[$dependency]) && !isset($installed_modules[$dependency])) {
        $dependencies[$dependency] = $dependency;
      }
    }
    return $dependencies;
  }

  /**
   * Tests whether the default search was correctly installed.
   */
  protected function testDefaultSetupWorking() {
    $server = Server::load('default_server');
    $this->assertTrue($server, 'Server can be loaded');

    $index = Index::load('default_index');
    $this->assertTrue($index, 'Index can be loaded');

    $this->drupalGet('search/content');
    $this->assertResponse(200, 'Anonymous user can access the search page.');
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('search/content');
    $this->assertResponse(200, 'Authenticated user can access the search page.');
  }

  /**
   * Creates a new image field.
   *
   * Copied from Drupal\image\Tests\ImageFieldTestBase.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $type_name
   *   The node type that this field will be added to.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @return \Drupal\field\Entity\FieldConfig
   *   The newly created field configuration.
   */
  protected function createImageField($name, $type_name, $storage_settings = array(), $field_settings = array(), $widget_settings = array()) {
    \Drupal::entityManager()
      ->getStorage('field_storage_config')
      ->create(array(
        'field_name' => $name,
        'entity_type' => 'node',
        'type' => 'image',
        'settings' => $storage_settings,
        'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
      ))->save();

    $field_config = \Drupal::entityManager()
      ->getStorage('field_config')
      ->create(array(
        'field_name' => $name,
        'label' => $name,
        'entity_type' => 'node',
        'bundle' => $type_name,
        'required' => !empty($field_settings['required']),
        'settings' => $field_settings,
      ));
    $field_config->save();

   $this->getEntityDisplay('form', 'node', $type_name, 'default')
      ->setComponent($name, array(
        'type' => 'image_image',
        'settings' => $widget_settings,
      ))
      ->save();

   $this->getEntityDisplay('view', 'node', $type_name, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;
  }

  /**
   * Retrieves an entity's view or form display.
   *
   * @param string $type
   *   The type of display, either "view" or "form".
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $mode
   *   The view or form mode.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The requested display.
   */
  protected function getEntityDisplay($type, $entity_type, $bundle, $mode) {
    // Try loading the entity from configuration.
    $display_storage = \Drupal::entityManager()->getStorage("entity_{$type}_display");
    $display = $display_storage->load($entity_type . '.' . $bundle . '.' . $mode);

    // If not found, create a fresh configuration object.
    if (!$display) {
      $display = $display_storage->create(array(
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $mode,
        'status' => TRUE,
      ));
    }

    return $display;
  }

}
