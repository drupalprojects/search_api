<?php
/**
 * @file
 * Definition of \Drupal\search_api\Tests\SearchApiWebTestBase.
 */

namespace Drupal\search_api\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides the base class for web tests for Search API.
 */
abstract class SearchApiWebTestBase extends WebTestBase {

  public static $modules = array('node', 'search_api');

  protected $adminUser;
  protected $anonymousUser;
  protected $urlGenerator;

  public function setUp() {
    parent::setUp();
    // Create users.
    $this->adminUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->anonymousUser = $this->drupalCreateUser(array());

    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create a node page type.
    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));
  }

  public function getTestServer($name = 'WebTest server', $machine_name = 'webtest_server', $service_id = 'search_api_test_service', $options = array(), $reset = FALSE) {
    /** @var $server \Drupal\search_api\Entity\Server */
    $server = entity_load('search_api_server', $machine_name);

    if ($server) {
      if ($reset) {
        $server->delete();
      }
    }
    else {
      $server = entity_create('search_api_server', array('name' => $name, 'machine_name' => $machine_name, 'servicePluginId' => $service_id));
      $server->description = $name;
      $server->save();
    }

    return $server;
  }

  public function getTestIndex($name = 'WebTest Index', $machine_name = 'webtest_index', $server_id = 'webtest_server', $datasource_plugin_id = 'search_api_content_entity_datasource:node', $reset = FALSE) {
    /** @var $index \Drupal\search_api\Entity\Index */
    $index = entity_load('search_api_index', $machine_name);

    if ($index) {
      if ($reset) {
        $index->delete();
      }
    }
    else {
      $index = entity_create('search_api_index', array('name' => $name, 'machine_name' => $machine_name, 'datasourcePluginId' => $datasource_plugin_id, 'serverMachineName' => $server_id));
      $index->description = $name;
      $index->save();
    }

    return $index;
  }
}
