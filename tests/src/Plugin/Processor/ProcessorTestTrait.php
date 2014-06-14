<?php
/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\ProcessorTestTrait.
 */

namespace Drupal\search_api\Tests\Plugin\Processor;

/**
 * Provides common methods for processor testing unit tests.
 */
trait ProcessorTestTrait {

  /**
   * The tested processor.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * Get an accessible method of the processor class using reflection.
   *
   * @param string $method_name
   *   The name of the method to return.
   *
   * @return \ReflectionMethod
   *   The requested method, marked as accessible.
   */
  public function getAccessibleMethod($method_name) {
    $class = new \ReflectionClass(get_class($this->processor));
    $method = $class->getMethod($method_name);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Invokes a method on the processor.
   *
   * @param string $method_name
   *   The method's name.
   * @param array $args
   *   The arguments to pass in the method call.
   */
  public function invokeMethod($method_name, array $args) {
    $method = $this->getAccessibleMethod($method_name);
    $method->invokeArgs($this->processor, $args);
  }

}
