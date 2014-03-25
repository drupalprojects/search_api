<?php

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "search_api_test_processor",
 *   label = @Translation("Test processor"),
 *   description = @Translation("Dummy processor implementation")
 * )
 */
class TestProcessor extends FieldsProcessorPluginBase {

}
