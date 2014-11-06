<?php

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Tests\IntegrationTest;

class NodeStatusIntegrationTest extends IntegrationTest {

  public function testNodeStatusIntegration() {
    $this->drupalLogin($this->adminUser);

    $this->serverId = $this->createServer();
    $this->createIndex();
    $this->trackContent();

    $this->addFilter('node_status');
  }
}
