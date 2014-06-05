<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFieldsForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a fields form controller for the Index entity.
 */
class IndexFieldsForm extends EntityForm {

  /**
   * The index for which the fields are configured.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index_fields';
  }

  /**
   * If getFormId is implemented, we do not need getBaseFormID().
   *
   * {@inheritdoc}
   */
  public function getBaseFormID() {
    return NULL;
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
   * Constructs a IndexFieldsForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    return new static($entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    // Get the index
    $index = $this->entity;

    // Set a proper title
    $form['#title'] = $this->t('Manage fields for search index @label', array('@label' => $index->label()));

    // Get all options
    $form_state['index'] = $index;
    $form['description'] = array(
      '#type' => 'item',
      '#title' => t('Select fields to index'),
      '#description' => t('<p>The datatype of a field determines how it can be used for searching and filtering.' .
        'The boost is used to give additional weight to certain fields, e.g. titles or tags.</p>' .
        '<p>Whether detailed field types are supported depends on the type of server this index resides on. ' .
        'In any case, fields of type "Fulltext" will always be fulltext-searchable.</p>'),
    );
    if ($index->hasValidServer()) {
      $form['description']['#description'] .= '<p>' . t('Check the <a href="@server-url">' . "server's</a> backend class description for details.",
          array('@server-url' => url($index->getServer()->getSystemPath('canonical')))) . '</p>';
    }

    if ($fields = $index->getFieldsByDatasource(NULL, FALSE)) {
      $form['_general'] = array(
        '#type' => 'details',
        '#title' => t('General'),
        '#open' => TRUE,
        '#theme' => 'search_api_admin_fields_table',
      );

      $additional = $index->getAdditionalFieldsByDatasource(NULL);
      $form['_general'] += $this->buildFields($fields, $additional);
    }

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $form[$datasource_id] = array(
        '#type' => 'details',
        '#title' => $datasource->label(),
        '#open' => TRUE,
        '#theme' => 'search_api_admin_fields_table',
      );

      $fields = $index->getFieldsByDatasource($datasource_id, FALSE);
      $additional = $index->getAdditionalFieldsByDatasource($datasource_id);
      $form[$datasource_id] += $this->buildFields($fields, $additional);
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save changes'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * Builds the form fields for a set of fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   List of fields to display.
   * @param \Drupal\search_api\Item\AdditionalFieldInterface[] $additional
   *   List of additional fields that can be added.
   *
   * @return array
   *   The build structure.
   */
  protected function buildFields(array $fields, array $additional) {
    // An array of option arrays for types, keyed by nesting level.
    $types = Utility::getDataTypes();

    $fulltext_types = array('text');
    // Add all custom data types with fallback "text" to fulltext types as well.
    foreach (Utility::getDataTypeInfo() as $id => $type) {
      if ($type['fallback'] != 'text') {
        continue;
      }
      $fulltext_types[] = $id;
    }

    $boost_values = array('0.1', '0.2', '0.3', '0.5', '0.8', '1.0', '2.0', '3.0', '5.0', '8.0', '13.0', '21.0');
    $boosts = array_combine($boost_values, $boost_values);

    $build['fields']['#tree'] = TRUE;

    foreach ($fields as $key => $field) {
      $build['fields'][$key]['title']['#markup'] = String::checkPlain($field->getLabel());
      $build['fields'][$key]['machine_name']['#markup'] = String::checkPlain($key);
      if ($field->getDescription()) {
        $build['fields'][$key]['description'] = array(
          '#type' => 'value',
          '#value' => $field->getDescription(),
        );
      }
      $build['fields'][$key]['indexed'] = array(
        '#type' => 'checkbox',
        '#default_value' => $field->isIndexed(),
      );
      $css_key = '#edit-fields-' . drupal_clean_css_identifier($key);
      $build['fields'][$key]['type'] = array(
        '#type' => 'select',
        '#options' => $types,
        '#default_value' => $field->getType(),
        '#states' => array(
          'visible' => array(
            $css_key . '-indexed' => array('checked' => TRUE),
          ),
        ),
      );
      $build['fields'][$key]['boost'] = array(
        '#type' => 'select',
        '#options' => $boosts,
        '#default_value' => sprintf('%.1f', $field->getBoost()),
        '#states' => array(
          'visible' => array(
            $css_key . '-indexed' => array('checked' => TRUE),
          ),
        ),
      );
      foreach ($fulltext_types as $type) {
        $build['fields'][$key]['boost']['#states']['visible'][$css_key . '-type'][] = array('value' => $type);
      }
    }

    if ($additional) {
      // Build our options.
      $build['additional'] = array(
        '#type' => 'details',
        '#title' => t('Related fields'),
        '#description' => t(
            'There are entities related to entities of this type. ' .
            'You can add their fields to the list above so they can be indexed too.'
          ) . '<br />',
        '#open' => TRUE,
        '#tree' => TRUE,
      );
      foreach ($additional as $key => $additional_field) {
        // We need to loop through each option because we need to disable the
        // checkbox if it's a dependency for another option.
        $build['additional']['field'][$key] = array(
          '#type' => 'checkbox',
          '#title' => $additional_field->getLabel(),
          '#default_value' => $additional_field->isEnabled(),
          '#disabled' => $additional_field->isLocked(),
        );
      }
      $build['additional']['add'] = array(
        '#type' => 'submit',
        '#value' => t('Update'),
      );
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $form_state['index'];

    // Store the fields configuration.
    $fields = $form_state['values']['fields'];
    foreach ($index->getFields(FALSE) as $field_id => $field) {
      if (isset($fields[$field_id])) {
        $field->setType($fields[$field_id]['type']);
        $field->setBoost($fields[$field_id]['boost']);
        $field->setIndexed((bool) $fields[$field_id]['indexed'], TRUE);
      }
    }

    // Store the additional fields configuration.
    if (isset($form_state['values']['additional']['field'])) {
      $additional = $form_state['values']['additional']['field'];
      foreach ($index->getAdditionalFields() as $field_id => $additional_field) {
        $additional_field->setEnabled(!empty($additional[$field_id]), TRUE);
      }
    }

    $index->save();

    // Show a different message based on the button.
    if ($form_state['values']['op'] == t('Save changes')) {
      drupal_set_message(t('The indexed fields were successfully changed.'));
    }
    else {
      drupal_set_message(t('The available fields were successfully changed.'));
    }
  }

}
