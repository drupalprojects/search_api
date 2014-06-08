<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\RoleFilter
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Utility\String;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

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
   *
   * This plugin only supports indexes containing users.
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'user') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    $roles = $this->configuration['roles'];
    $default = (bool) $this->configuration['default'];

    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $account = $item->getOriginalObject();
      if (!($account instanceof UserInterface)) {
        continue;
      }

      // @todo Could probably be simplified with array_intersect_key() and
      // if ($default == $has_some_roles).
      $excess_roles = array_diff_key($account->getRoles(), $roles);

      // All but those from one of the selected roles.
      if ($default) {
        // User has some of the selected roles.
        if (count($excess_roles) < count($account->getRoles())) {
          unset($items[$item_id]);
        }
      }
      // Only those from the selected roles.
      else {
        // User does not have any of the selected roles.
        if (count($excess_roles) === count($account->getRoles())) {
          unset($items[$item_id]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $options = array_map(function (RoleInterface $item) {
      return String::checkPlain($item->label());
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
