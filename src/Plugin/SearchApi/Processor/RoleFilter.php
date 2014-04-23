<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\RoleFilter
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

 /**
  * @SearchApiProcessor(
  *   id = "search_api_role_filter_processor",
  *   label = @Translation("Role Filter"),
  *   description = @Translation("Data alteration that filters out users based on their role.")
  * )
  */
class RoleFilter extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   * This plugin only supports indexes containing users.
   */
  public static function supportsIndex(IndexInterface $index) {
    // @todo Re-introduce Datasource::getEntityType() for this?
    foreach ($index->getDatasources() as $datasource) {
      $definition = $datasource->getPluginDefinition();
      if (isset($definition['entity_type']) && $definition['entity_type'] === 'user') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterItems(array &$items) {
    $roles = $this->configuration['roles'];
    $default = (bool) $this->configuration['default'];
    foreach ($items as $id => $account) {
      $role_match = (count(array_diff_key($account->roles, $roles)) !== count($account->roles));
      if ($role_match === $default) {
        unset($items[$id]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $options = array_map(function ($item) {
      return check_plain($item->label());
    }, user_roles());

    $form['default'] = array(
      '#type' => 'radios',
      '#title' => t('Which users should be indexed?'),
      '#default_value' => isset($this->configuration['default']) ? $this->configuration['default'] : 1,
      '#options' => array(
        1 => t('All but those from one of the selected roles'),
        0 => t('Only those from the selected roles'),
      ),
    );
    $form['roles'] = array(
      '#type' => 'select',
      '#title' => t('Roles'),
      '#default_value' => isset($this->configuration['roles']) ? $this->configuration['roles'] : array(),
      '#options' => $options,
      '#size' => min(4, count($options)),
      '#multiple' => TRUE,
    );
    return $form;
  }
}
