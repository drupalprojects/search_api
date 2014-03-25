<?php
/**
 * @file
 * Definition of \Drupal\search_api\Tests\search_apiTest.
 */

namespace Drupal\search_api\Tests;
use Drupal\simpletest\WebTestBase;


class SearchApiCreateServerTest extends WebTestBase {

    public static $modules = array('search_api');

    protected $test_user;

    /**
     * {@inheritdoc}
     */
    public static function getInfo() {
        return array(
            'name' => 'Search API create server and index',
            'description' => 'Tests that the Search API servers and indexes can be created.',
            'group' => 'Search API',
        );
    }

    public function setUp() {
        parent::setUp();

        // Create users.
        $this->test_user = $this->drupalCreateUser(array('administer search_api'));
    }

    public function testSearchApiCreateServer() {

        $this->createServer();
    }

    public function createServer() {
        $settings_path = 'admin/config/search/search_api/add_server';

        $this->drupalLogin($this->test_user);
        $this->drupalGet($settings_path);
        $this->assertResponse(200);
        $edit = array(
            'name' => '',
            'status' => 1,
            'description' => 'A server used for testing.',
            'servicePluginId' => '',
        );

        $this->drupalPostForm($settings_path, $edit, t('Save'));
        $this->assertText(t('!name field is required.', array('!name' => t('Server name'))));
        $this->assertText(t('!name field is required.', array('!name' => t('Service'))));


    }
}
