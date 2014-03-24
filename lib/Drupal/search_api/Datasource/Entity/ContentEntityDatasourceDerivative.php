<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Entity\ContentEntityDatasourceDerivative.
 */

namespace Drupal\search_api\Datasource\Entity;

use Drupal\Core\Entity\ContentEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeInterface;
use Drupal\Core\Entity\EntityManager;

/**
 * Provides a datasource plugin definition for every content entity type.
 */
class ContentEntityDatasourceDerivative implements ContainerDerivativeInterface {

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  private $derivatives;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

  /**
   * Create a ContentEntityDatasourceDerivative object.
   *
   * @param \Drupal\Core\Entity\EntityManager $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManager $entity_manager) {
    // Setup object members.
    $this->entityManager = $entity_manager;
    $this->derivatives = array();
  }

  /**
   * Get the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManager
   *   An instance of EntityManager.
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    // Get the derivatives for the given base plugin definition.
    $derivatives = $this->getDerivativeDefinitions($base_plugin_definition);
    // Get the derivative plugin defintion.
    return isset($derivatives[$derivative_id]) ? $derivatives[$derivative_id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Get the base plugin ID.
    $base_plugin_id = $base_plugin_definition['id'];
    // Check if the derivatives need to be resolved.
    if (!isset($this->derivatives[$base_plugin_id])) {
      // Get the entity manager.
      $entity_manager = $this->getEntityManager();
      // Initialize the plugin derivatives variable to an empty array.
      $plugin_derivatives = array();
      // Iterate through the entity types.
      foreach ($entity_manager->getDefinitions() as $entity_type => $entity_type_definition) {
        // Check if the entity type is not a configuration entity.
        if ($entity_type_definition instanceof ContentEntityType) {
          // Build the derivative plugin definition.
          $plugin_derivatives["{$entity_type}"] = array(
            'id' => "entity:{$entity_type}",
            'entity_type' => $entity_type,
            'label' => $entity_type_definition->getLabel(),
          ) + $base_plugin_definition;
        }
      }

      // Sort alphabetically
      uasort($plugin_derivatives, array($this, 'sortDerivatives'));

      // Add the plugin derivatives for the given base plugin.
      $this->derivatives[$base_plugin_id] = $plugin_derivatives;
    }
    return $this->derivatives[$base_plugin_id];
  }

  /**
   * Helper function to sort the list of content entities
   */
  function sortDerivatives($a, $b) {
    return strcmp($a["label"], $b["label"]);
  }

}
