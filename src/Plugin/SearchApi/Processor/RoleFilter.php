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
   * Overrides \Drupal\search_api\Processor\ProcessorPluginBase::supportsIndex().
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
  public function defaultConfiguration() {
    return array(
      'default' => TRUE,
      'roles' => array(),
    );
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
      '#default_value' => $this->configuration['default'],
      '#options' => array(
        1 => t('All but those from one of the selected roles'),
        0 => t('Only those from the selected roles'),
      ),
    );
    $form['roles'] = array(
      '#type' => 'select',
      '#title' => t('Roles'),
      '#default_value' => $this->configuration['roles'],
      '#options' => $options,
      '#size' => min(4, count($options)),
      '#multiple' => TRUE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    $form_state['values']['roles'] = array_filter($form_state['values']['roles']);
    $form_state['values']['default'] = (bool) $form_state['values']['default'];

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    $selected_roles = $this->configuration['roles'];
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
      $account_roles = $account->getRoles();
      $account_roles = array_combine($account_roles, $account_roles);
      $excess_roles = array_diff_key($account_roles, $selected_roles);

      // All but those from one of the selected roles.
      if ($default) {
        // User has some of the selected roles.
        if (count($excess_roles) < count($account_roles)) {
          unset($items[$item_id]);
        }
      }
      // Only those from the selected roles.
      else {
        // User does not have any of the selected roles.
        if (count($excess_roles) === count($account_roles)) {
          unset($items[$item_id]);
        }
      }
    }
  }

}
