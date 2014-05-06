<?php

/**
 * @file
 * Contains Drupal\search_api\Tests\TestComplexDataInterface.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\TypedData\ComplexDataInterface;

/**
 * Provides a testable version of \Drupal\Core\TypedData\ComplexDataInterface.
 *
 * @see https://github.com/sebastianbergmann/phpunit-mock-objects/issues/103
 */
interface TestComplexDataInterface extends \Iterator, ComplexDataInterface {
}
