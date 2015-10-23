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
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'standard';

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

}
