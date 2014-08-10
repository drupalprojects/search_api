<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasourceDeriver.
 */

namespace Drupal\search_api\Plugin\SearchApi\Datasource;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a datasource plugin definition for every content entity type.
 */
class ContentEntityDatasourceDeriver implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives = array();

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    /** @var \Drupal\search_api\Plugin\SearchApi\Datasource\ContentEntityDatasourceDeriver $deriver */
    $deriver = new static();

    /** @var $entity_manager \Drupal\Core\Entity\EntityManagerInterface */
    $entity_manager = $container->get('entity.manager');
    $deriver->setEntityManager($entity_manager);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $container->get('string_translation');
    $deriver->setStringTranslation($translation);

    return $deriver;
  }

  /**
   * Retrieves the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  public function getEntityManager() {
    return $this->entityManager ?: \Drupal::entityManager();
  }

  /**
   * Sets the entity manager.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   *
   * @return $this
   */
  public function setEntityManager(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
    return $this;
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
      // Initialize the plugin derivatives variable to an empty array.
      $plugin_derivatives = array();
      // Iterate through the entity types.
      foreach ($this->getEntityManager()->getDefinitions() as $entity_type => $entity_type_definition) {
        // Check if the entity type is not a configuration entity.
        if ($entity_type_definition instanceof ContentEntityType) {
          // Build the derivative plugin definition.
          $plugin_derivatives[$entity_type] = array(
            'id' => $base_plugin_id . PluginBase::DERIVATIVE_SEPARATOR . $entity_type,
            'entity_type' => $entity_type,
            'label' => $entity_type_definition->getLabel(),
            'description' => $this->t('Provides %entity_type entities for indexing and searching.', array('%entity_type' => $entity_type_definition->getLabel())),
          ) + $base_plugin_definition;
        }
      }

      // Sort alphabetically
      uasort($plugin_derivatives, array($this, 'compareDerivatives'));

      // Add the plugin derivatives for the given base plugin.
      $this->derivatives[$base_plugin_id] = $plugin_derivatives;
    }
    return $this->derivatives[$base_plugin_id];
  }

  /**
   * Helper function to sort the list of content entities
   */
  public function compareDerivatives($a, $b) {
    return strcmp($a['label'], $b['label']);
  }

}
