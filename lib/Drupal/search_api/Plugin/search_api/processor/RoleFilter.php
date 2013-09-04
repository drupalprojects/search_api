<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\RoleFilter.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Filters out users based on their role.
 *
 * @SearchApiProcessor(
 *   id = "search_api_bundle_filter",
 *   name = @Translation("Bundle filter"),
 *   description = @Translation("Exclude items from indexing based on their bundle (content type, vocabulary, …)."),
 *   weight = -20
 * )
 */
class RoleFilter extends ProcessorPluginBase {

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * This plugin only supports indexes containing users.
   */
  public static function supportsIndex(IndexInterface $index) {
    return $index->getEntityType() == 'user';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $options = array_map('check_plain', user_roles());
    $form = array(
      'default' => array(
        '#type' => 'radios',
        '#title' => t('Which users should be indexed?'),
        '#default_value' => isset($this->options['default']) ? $this->options['default'] : 1,
        '#options' => array(
          1 => t('All but those from one of the selected roles'),
          0 => t('Only those from the selected roles'),
        ),
      ),
      'roles' => array(
        '#type' => 'select',
        '#title' => t('Roles'),
        '#default_value' => isset($this->options['roles']) ? $this->options['roles'] : array(),
        '#options' => $options,
        '#size' => min(4, count($options)),
        '#multiple' => TRUE,
      ),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    $roles = $this->options['roles'];
    $default = (bool) $this->options['default'];
    foreach ($items as $id => $account) {
      $role_match = (count(array_diff_key($account->roles, $roles)) !== count($account->roles));
      if ($role_match === $default) {
        unset($items[$id]);
      }
    }
  }

}
