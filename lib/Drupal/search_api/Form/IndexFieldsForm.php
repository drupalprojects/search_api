<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFieldsForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityFormController;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a fields form controller for the Index entity.
 */
class IndexFieldsForm extends EntityFormController {

  /**
   * The index where the fields will be configured for
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $entity;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  private $entityManager;

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
   * Constructs a ContentEntityFormController object.
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
    $options = $index->getFields(FALSE, TRUE);
    $fields = $options['fields'];

    $additional = $options['additional fields'];

    // An array of option arrays for types, keyed by nesting level.
    $types = array(0 => search_api_data_types());

    // Get all entity types
    $entity_types = $this->getEntityManager()->getDefinitions();
    $boost_values = array('0.1', '0.2', '0.3', '0.5', '0.8', '1.0', '2.0', '3.0', '5.0', '8.0', '13.0', '21.0');
    $boosts = array_combine($boost_values, $boost_values);

    $fulltext_types = array(0 => array('text'));
    // Add all custom data types with fallback "text" to fulltext types as well.
    foreach (search_api_get_data_type_info() as $id => $type) {
      if ($type['fallback'] != 'text') {
        continue;
      }
      $fulltext_types[0][] = $id;
    }

    $form_state['index'] = $index;
    $form['#theme'] = 'search_api_admin_fields_table';
    $form['#tree'] = TRUE;
    $form['description'] = array(
      '#type' => 'item',
      '#title' => t('Select fields to index'),
      '#description' => t('<p>The datatype of a field determines how it can be used for searching and filtering.' .
        'The boost is used to give additional weight to certain fields, e.g. titles or tags.</p>' .
        '<p>Whether detailed field types are supported depends on the type of server this index resides on. ' .
        'In any case, fields of type "Fulltext" will always be fulltext-searchable.</p>'),
    );
    if ($index->getServer()) {
      $form['description']['#description'] .= '<p>' . t('Check the <a href="@server-url">' . "server's</a> service class description for details.",
          array('@server-url' => url($index->getServer()->getSystemPath('canonical')))) . '</p>';
    }
    foreach ($fields as $key => $info) {
      $form['fields'][$key]['title']['#markup'] = String::checkPlain($info['name']);
      $form['fields'][$key]['machine_name']['#markup'] = String::checkPlain($key);
      if (isset($info['description'])) {
        $form['fields'][$key]['description'] = array(
          '#type' => 'value',
          '#value' => $info['description'],
        );
      }
      $form['fields'][$key]['indexed'] = array(
        '#type' => 'checkbox',
        '#default_value' => $info['indexed'],
      );
      if (empty($info['entity_type'])) {
        // Determine the correct type options (with the correct nesting level).
        //$level = search_api_list_nesting_level($info['type']);
        $level = 1;
        if (empty($types[$level])) {
          $types[$level] = array();
          foreach ($types[0] as $type => $name) {
            // We use the singular name for list types, since the user usually
            // doesn't care about the nesting level.
            $types[$level][$type] = $name;
          }
          foreach ($fulltext_types[0] as $type) {
            $fulltext_types[$level][] = $type;
          }
        }
        $css_key = '#edit-fields-' . drupal_clean_css_identifier($key);
        $form['fields'][$key]['type'] = array(
          '#type' => 'select',
          '#options' => $types[$level],
          '#default_value' => isset($info['real_type']) ? $info['real_type'] : $info['type'],
          '#states' => array(
            'visible' => array(
              $css_key . '-indexed' => array('checked' => TRUE),
            ),
          ),
        );
        $form['fields'][$key]['boost'] = array(
          '#type' => 'select',
          '#options' => $boosts,
          '#default_value' => (isset($info['boost'])) ? $info['boost'] : '',
          '#states' => array(
            'visible' => array(
              $css_key . '-indexed' => array('checked' => TRUE),
            ),
          ),
        );
        foreach ($fulltext_types[$level] as $type) {
          $form['fields'][$key]['boost']['#states']['visible'][$css_key . '-type'][] = array('value' => $type);
        }
      }
      else {
        // This is an entity.
        $label = $entity_types[$info['entity_type']]['label'];
        if (!isset($entity_description_added)) {
          $form['description']['#description'] .= '<p>' .
            t('Note that indexing an entity-valued field (like %field, which has type %type) directly will only index the entity ID. ' .
              'This will be used for filtering and also sorting (which might not be what you expect). ' .
              'The entity label will usually be used when displaying the field, though. ' .
              'Use the "Add related fields" option at the bottom for indexing other fields of related entities.',
              array('%field' => $info['name'], '%type' => $label)) . '</p>';
          $entity_description_added = TRUE;
        }
        $form['fields'][$key]['type'] = array(
          '#type' => 'value',
          '#value' => $info['type'],
        );
        $form['fields'][$key]['entity_type'] = array(
          '#type' => 'value',
          '#value' => $info['entity_type'],
        );
        $form['fields'][$key]['type_name'] = array(
          '#markup' => String::checkPlain($label),
        );
        $form['fields'][$key]['boost'] = array(
          '#type' => 'value',
          '#value' => $info['boost'],
        );
        $form['fields'][$key]['boost_text'] = array(
          '#markup' => '&nbsp;',
        );
      }
      if ($key == 'search_api_language') {
        // Is treated specially to always index the language.
        $form['fields'][$key]['type']['#default_value'] = 'string';
        $form['fields'][$key]['type']['#disabled'] = TRUE;
        $form['fields'][$key]['boost']['#default_value'] = '1.0';
        $form['fields'][$key]['boost']['#disabled'] = TRUE;
        $form['fields'][$key]['indexed']['#default_value'] = 1;
        $form['fields'][$key]['indexed']['#disabled'] = TRUE;
      }
    }

    if ($additional) {
      // Build our options and our selected ones
      $additional_form_options = array();
      $additional_form_default_values = array();
      foreach ($additional as $additional_key => $additional_option) {
        $additional_form_options[$additional_key] = $additional_option['name'];
        $additional_form_default_values[$additional_key] = (!empty($additional_option['enabled'])) ? $additional_key : 0;
      }

      $form['additional'] = array(
        '#type' => 'details',
        '#title' => t('Related fields'),
        '#description' => t('There are entities related to entities of this type. ' .
            'You can add their fields to the list above so they can be indexed too.') . '<br />',
        '#open' => TRUE,
      );
      foreach ($additional as $additional_key => $additional_option) {
        // We need to loop through each option because we need to disable the
        // checkbox if it's a dependency for another option.
        $form['additional']['field'][$additional_key] = array(
          '#type' => 'checkbox',
          '#title' =>  $additional_option['name'],
          '#default_value' => (!empty($additional_option['enabled'])) ? 1 : 0,
          '#disabled' => (!empty($additional_option['dependency'])) ? TRUE : FALSE,
        );
      }
      $form['additional']['add'] = array(
        '#type' => 'submit',
        '#value' => t('Update'),
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save changes'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $form_state['index'];
    $options = isset($index->getOptions) ? $index->getOptions : array();

    $fields = $form_state['values']['fields'];
    foreach ($fields as $name => $field) {
      if (empty($field['indexed'])) {
        unset($fields[$name]);
      }
      else {
        // Don't store the description. "indexed" is implied.
        unset($fields[$name]['description'], $fields[$name]['indexed']);
        // Boost defaults to 1.0.
        if ($field['boost'] == '1.0') {
          unset($fields[$name]['boost']);
        }
      }
    }
    // Store the fields info
    $options['fields'] = $fields;

    // Store the additional fields info
    $additional = $form_state['values']['additional']['field'];
    $options['additional fields'] = $additional;

    $index->setOptions($options);
    $ret = $index->save();

    // Show a different message based on the button
    if ($form_state['values']['op'] == t('Save changes')) {
      if ($ret) {
        drupal_set_message(t('The indexed fields were successfully changed. ' .
          'The index was cleared and will have to be re-indexed with the new settings.'));
      }
      else {
        drupal_set_message(t('No values were changed.'));
      }
    }
    else {
      if ($ret) {
        drupal_set_message(t('The available fields were successfully changed.'));
      }
      else {
        drupal_set_message(t('No values were changed.'));
      }
    }
  }

}
