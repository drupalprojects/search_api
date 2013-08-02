<?php
/**
 * @file
 * Database additions for Search API upgrade tests.
 *
 * This dump only contains data and schema components relevant for Search API
 * functionality. The system module's drupal-7.bare.minimal.database.php.gz file
 * is imported before this dump, so the two form the database structure expected
 * in tests together.
 */

// Add the administrator role.
db_insert('role')->fields(array(
  'rid' => '3',
  'name' => 'administrator',
  'weight' => '2',
))
    ->execute();

// Grant Search API admin rights to the administrator role.
db_insert('role_permission')->fields(array(
  'rid' => '3',
  'permission' => 'administer search_api',
  'module' => 'search_api',
))
    ->execute();

// Create the index table, add an index.
db_create_table('search_api_index', array(
  'fields' => array(
    'id' => array(
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ),
    'name' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'machine_name' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'description' => array(
      'type' => 'text',
      'not null' => FALSE,
    ),
    'server' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => FALSE,
    ),
    'item_type' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'options' => array(
      'type' => 'text',
      'size' => 'medium',
      'serialize' => TRUE,
      'not null' => TRUE,
    ),
    'enabled' => array(
      'type' => 'int',
      'size' => 'tiny',
      'not null' => TRUE,
      'default' => 1,
    ),
    'read_only' => array(
      'type' => 'int',
      'size' => 'tiny',
      'not null' => TRUE,
      'default' => 0,
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
      'size' => 'tiny',
    ),
    'module' => array(
      'type' => 'varchar',
      'length' => 255,
      'not null' => FALSE,
    ),
  ),
  'indexes' => array(
    'item_type' => array(
      'item_type',
    ),
    'server' => array(
      'server',
    ),
    'enabled' => array(
      'enabled',
    ),
  ),
  'unique keys' => array(
    'machine_name' => array(
      'machine_name',
    ),
  ),
  'primary key' => array(
    'id',
  ),
  'module' => 'search_api',
  'name' => 'search_api_index',
));
$index_options = array(
  'index_directly' => 1,
  'cron_limit' => -1,
  'data_alter_callbacks' => array(
    'search_api_alter_add_viewed_entity' => array(
      'status' => 1,
      'weight' => 0,
      'settings' => array(
        'mode' => 'full',
      ),
    ),
  ),
  'processors' => array(
    'search_api_case_ignore' => array(
      'status' => 1,
      'weight' => 0,
      'settings' => array(
        'fields' => array(
          'body:value' => '1',
        ),
      ),
    ),
  ),
  'fields' => array(
    'type' => array(
      'type' => 'string',
    ),
    'title' => array(
      'type' => 'text',
      'boost' => 5.0,
    ),
    'status' => array(
      'type' => 'boolean',
    ),
    'body:value' => array(
      'type' => 'text',
    ),
  ),
);
db_insert('search_api_index')->fields(array(
  'id' => '1',
  'name' => 'Test index',
  'machine_name' => 'test_index',
  'description' => 'An automatically created search index for indexing node data. Might be configured to specific needs.',
  'server' => 'test_server',
  'item_type' => 'node',
  'options' => serialize($index_options),
  'enabled' => '1',
  'status' => '1',
  'module' => NULL,
  'read_only' => '0',
))
    ->execute();

// Create search item table with a few entries.
db_create_table('search_api_item', array(
  'fields' => array(
    'item_id' => array(
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ),
    'index_id' => array(
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ),
    'changed' => array(
      'type' => 'int',
      'size' => 'big',
      'not null' => TRUE,
      'default' => 1,
    ),
  ),
  'indexes' => array(
    'indexing' => array(
      'index_id',
      'changed',
    ),
  ),
  'primary key' => array(
    'item_id',
    'index_id',
  ),
  'module' => 'search_api',
  'name' => 'search_api_item',
));
db_insert('search_api_item')->fields(array(
    'item_id',
    'index_id',
    'changed',
  ))
  ->values(array(
    'item_id' => '1',
    'index_id' => '1',
    'changed' => '0',
  ))
  ->values(array(
    'item_id' => '2',
    'index_id' => '1',
    'changed' => '0',
  ))
  ->values(array(
    'item_id' => '3',
    'index_id' => '1',
    'changed' => '0',
  ))
  ->execute();

// Create search server table and test server.
db_create_table('search_api_server', array(
  'fields' => array(
    'id' => array(
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ),
    'name' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'machine_name' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'description' => array(
      'type' => 'text',
      'not null' => FALSE,
    ),
    'class' => array(
      'type' => 'varchar',
      'length' => 50,
      'not null' => TRUE,
    ),
    'options' => array(
      'type' => 'text',
      'size' => 'medium',
      'serialize' => TRUE,
      'not null' => TRUE,
    ),
    'enabled' => array(
      'type' => 'int',
      'size' => 'tiny',
      'not null' => TRUE,
      'default' => 1,
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
      'size' => 'tiny',
    ),
    'module' => array(
      'type' => 'varchar',
      'length' => 255,
      'not null' => FALSE,
    ),
  ),
  'indexes' => array(
    'enabled' => array(
      'enabled',
    ),
  ),
  'unique keys' => array(
    'machine_name' => array(
      'machine_name',
    ),
  ),
  'primary key' => array(
    'id',
  ),
  'module' => 'search_api',
  'name' => 'search_api_server',
));
$server_settings['foo'] = 'bar';
db_insert('search_api_server')->fields(array(
    'id' => '1',
    'name' => 'Test server',
    'machine_name' => 'test_server',
    'description' => '',
    'class' => 'search_api_test',
    'options' => serialize($server_settings),
    'enabled' => '1',
    'status' => '1',
    'module' => NULL,
  ))
  ->execute();

// Enable the Entity API and Search API modules.
db_insert('system')->fields(array(
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
    'info',
  ))
  ->values(array(
    'filename' => 'sites/all/modules/entity/entity.module',
    'name' => 'entity',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7002',
    'weight' => '0',
    'info' => 'a:9:{s:4:"name";s:10:"Entity API";s:11:"description";s:69:"Enables modules to work with any entity type and to provide entities.";s:4:"core";s:3:"7.x";s:5:"files";a:24:{i:0;s:19:"entity.features.inc";i:1;s:15:"entity.i18n.inc";i:2;s:15:"entity.info.inc";i:3;s:16:"entity.rules.inc";i:4;s:11:"entity.test";i:5;s:19:"includes/entity.inc";i:6;s:30:"includes/entity.controller.inc";i:7;s:22:"includes/entity.ui.inc";i:8;s:27:"includes/entity.wrapper.inc";i:9;s:22:"views/entity.views.inc";i:10;s:52:"views/handlers/entity_views_field_handler_helper.inc";i:11;s:51:"views/handlers/entity_views_handler_area_entity.inc";i:12;s:53:"views/handlers/entity_views_handler_field_boolean.inc";i:13;s:50:"views/handlers/entity_views_handler_field_date.inc";i:14;s:54:"views/handlers/entity_views_handler_field_duration.inc";i:15;s:52:"views/handlers/entity_views_handler_field_entity.inc";i:16;s:51:"views/handlers/entity_views_handler_field_field.inc";i:17;s:53:"views/handlers/entity_views_handler_field_numeric.inc";i:18;s:53:"views/handlers/entity_views_handler_field_options.inc";i:19;s:50:"views/handlers/entity_views_handler_field_text.inc";i:20;s:49:"views/handlers/entity_views_handler_field_uri.inc";i:21;s:62:"views/handlers/entity_views_handler_relationship_by_bundle.inc";i:22;s:52:"views/handlers/entity_views_handler_relationship.inc";i:23;s:53:"views/plugins/entity_views_plugin_row_entity_view.inc";}s:12:"dependencies";a:0:{}s:7:"package";s:5:"Other";s:7:"version";N;s:3:"php";s:5:"5.2.4";s:9:"bootstrap";i:0;}',
  ))
  ->values(array(
    'filename' => 'sites/all/modules/search_api/search_api.module',
    'name' => 'search_api',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7114',
    'weight' => '0',
    'info' => 'a:10:{s:4:"name";s:10:"Search API";s:11:"description";s:63:"Provides a generic API for modules offering search capabilites.";s:12:"dependencies";a:1:{i:0;s:6:"entity";}s:4:"core";s:3:"7.x";s:7:"package";s:6:"Search";s:5:"files";a:26:{i:0;s:15:"search_api.test";i:1;s:21:"includes/callback.inc";i:2;s:37:"includes/callback_add_aggregation.inc";i:3;s:35:"includes/callback_add_hierarchy.inc";i:4;s:29:"includes/callback_add_url.inc";i:5;s:39:"includes/callback_add_viewed_entity.inc";i:6;s:35:"includes/callback_bundle_filter.inc";i:7;s:38:"includes/callback_language_control.inc";i:8;s:33:"includes/callback_node_access.inc";i:9;s:33:"includes/callback_node_status.inc";i:10;s:33:"includes/callback_role_filter.inc";i:11;s:23:"includes/datasource.inc";i:12;s:30:"includes/datasource_entity.inc";i:13;s:32:"includes/datasource_external.inc";i:14;s:22:"includes/exception.inc";i:15;s:25:"includes/index_entity.inc";i:16;s:22:"includes/processor.inc";i:17;s:32:"includes/processor_highlight.inc";i:18;s:34:"includes/processor_html_filter.inc";i:19;s:34:"includes/processor_ignore_case.inc";i:20;s:32:"includes/processor_stopwords.inc";i:21;s:32:"includes/processor_tokenizer.inc";i:22;s:38:"includes/processor_transliteration.inc";i:23;s:18:"includes/query.inc";i:24;s:26:"includes/server_entity.inc";i:25;s:20:"includes/service.inc";}s:9:"configure";s:30:"admin/config/search/search_api";s:7:"version";N;s:3:"php";s:5:"5.2.4";s:9:"bootstrap";i:0;}',
  ))
  ->execute();

// Set Search API variables.
$tasks['test']['test'][] = 'remove';
db_insert('variable')->fields(array(
    'name',
    'value',
  ))
  ->values(array(
    'name' => 'search_api_batch_per_cron',
    'value' => serialize(20),
  ))
  ->values(array(
    'name' => 'search_api_index_worker_callback_runtime',
    'value' => serialize(30),
  ))
  ->values(array(
    'name' => 'search_api_tasks',
    'value' => serialize($tasks),
  ))
  ->execute();
