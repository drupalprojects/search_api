<?php
/**
 * @file
 * Contains \Drupal\search_api\Form\IndexFieldsForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\search_api\Index\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\String;

/**
 * Provides a fields form controller for the Index entity.
 */
class IndexFieldsForm extends EntityForm {

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
  protected $entityManager;

  /**
   * The ID of the datasource plugin.
   *
   * @var string
   */
  protected $datasourceId;

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
    $options = $index->getFields(FALSE, TRUE);

    $form_state['index'] = $index;
    $form['description'] = array(
      '#type' => 'item',
      '#title' => t('Select fields to index'),
      '#description' => t('<p>The datatype of a field determines how it can be used for searching and filtering.' .
        'The boost is used to give additional weight to certain fields, e.g. titles or tags.</p>' .
        '<p>Whether detailed field types are supported depends on the type of server this index resides on. ' .
        'In any case, fields of type "Fulltext" will always be fulltext-searchable.</p>'),
    );
    if ($index->getServer()) {
      $form['description']['#description'] .= '<p>' . t('Check the <a href="@server-url">' . "server's</a> backend class description for details.",
          array('@server-url' => url($index->getServer()->getSystemPath('canonical')))) . '</p>';
    }

    if ($fields = $this->getDatasourceFields($options['fields'], NULL)) {
      $form['general'] = array(
        '#type' => 'details',
        '#title' => t('General'),
        '#open' => TRUE,
        '#theme' => 'search_api_admin_fields_table',
      );

      $additional = $this->getDatasourceFields($options['additional fields'], NULL);
      $form['general'] += $this->buildFields($fields, $additional);
    }

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $form[$datasource_id] = array(
        '#type' => 'details',
        '#title' => $datasource->label(),
        '#open' => TRUE,
        '#theme' => 'search_api_admin_fields_table',
      );

      $fields = $this->getDatasourceFields($options['fields'], $datasource_id);
      $additional = $this->getDatasourceFields($options['additional fields'], $datasource_id);
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
   * @param array $fields
   *   List of fields to display.
   * @param array $additional
   *   List of additional fields that can be added.
   *
   * @return array
   *   The build structure.
   */
  protected function buildFields(array $fields, array $additional) {

    // An array of option arrays for types, keyed by nesting level.
    $types = array(0 => search_api_data_types());

    $fulltext_types = array(0 => array('text'));
    // Add all custom data types with fallback "text" to fulltext types as well.
    foreach (search_api_get_data_type_info() as $id => $type) {
      if ($type['fallback'] != 'text') {
        continue;
      }
      $fulltext_types[0][] = $id;
    }

    $build = array();

    $boost_values = array('0.1', '0.2', '0.3', '0.5', '0.8', '1.0', '2.0', '3.0', '5.0', '8.0', '13.0', '21.0');
    $boosts = array_combine($boost_values, $boost_values);

    $build['fields']['#tree'] = TRUE;

    foreach ($fields as $key => $info) {
      $build['fields'][$key]['title']['#markup'] = String::checkPlain(
        $info['name']
      );
      $build['fields'][$key]['machine_name']['#markup'] = String::checkPlain(
        $key
      );
      if (isset($info['description'])) {
        $build['fields'][$key]['description'] = array(
          '#type' => 'value',
          '#value' => $info['description'],
        );
      }
      $build['fields'][$key]['indexed'] = array(
        '#type' => 'checkbox',
        '#default_value' => $info['indexed'],
      );
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
      $build['fields'][$key]['type'] = array(
        '#type' => 'select',
        '#options' => $types[$level],
        '#default_value' => isset($info['real_type']) ? $info['real_type'] : $info['type'],
        '#states' => array(
          'visible' => array(
            $css_key . '-indexed' => array('checked' => TRUE),
          ),
        ),
      );
      $build['fields'][$key]['boost'] = array(
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
        $build['fields'][$key]['boost']['#states']['visible'][$css_key . '-type'][] = array('value' => $type);
      }
    }

    if ($additional) {
      // Build our options and our selected ones

      // @todo: Figure out if this serves a purpose
      //$additional_form_options = array();
      //$additional_form_default_values = array();
      //foreach ($additional as $additional_key => $additional_option) {
      //  $additional_form_options[$additional_key] =  $additional_option['name'];
      //  $additional_form_default_values[$additional_key] = (!empty($additional_option['enabled'])) ? $additional_key : 0;
      //}

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
      foreach ($additional as $additional_key => $additional_option) {
        // We need to loop through each option because we need to disable the
        // checkbox if it's a dependency for another option.
        $build['additional']['field'][$additional_key] = array(
          '#type' => 'checkbox',
          '#title' => $additional_option['name'],
          '#default_value' => (!empty($additional_option['enabled'])) ? 1 : 0,
          '#disabled' => (!empty($additional_option['dependency'])) ? TRUE : FALSE,
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
    $options = isset($index->options) ? $index->options : array();

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
    if (!empty($options['fields'])) {
      $existing_fields = $this->getDatasourceFields($options['fields'], $this->datasourceId);
      $options['fields'] = array_diff_key($options['fields'], $existing_fields);
      $fields += $options['fields'];
    }
    $options['fields'] = $fields;

    // Store the additional fields info.
    if (isset($form_state['values']['additional']['field'])) {
      $additional = $form_state['values']['additional']['field'];
      if (!empty($options['additional fields'])) {
        $existing_additional_fields = $this->getDatasourceFields($options['additional fields'], $this->datasourceId);
        $options['additional fields'] = array_diff_key($options['additional fields'], $existing_additional_fields);
        $additional += $options['additional fields'];
      }
      $options['additional fields'] = $additional;
    }

    $index->setOptions($options);
    $ret = $index->save();

    // Show a different message based on the button.
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

  /**
   * Returns an array of fields available to a certain datasource.
   *
   * @param array $fields
   *   An array of fields as returned by
   *   \Drupal\search_api\Index\IndexInterface::getFields().
   * @param string|null $datasource_id
   *   (optional) The ID of the datasource plugin. If an empty value is given,
   *   all datasource-independent fields are returned.
   *
   * @return array
   *   An array of the same structure as the $fields argument, filtered by
   *   $datasource_id.
   */
  protected function getDatasourceFields($fields, $datasource_id = NULL) {
    // Unset all fields that aren't matching.
    foreach ($fields as $name => $field) {
      // This is a bit tricky: the strpos() will return FALSE if there is no
      // separator present, substr() will cast that to 0 and thus return an
      // empty string – which will equal NULL in the following if statement,
      // thus including such fields exactly when no datasource is specified.
      $field_datasource_id = substr($name, 0, strpos($name, IndexInterface::DATASOURCE_ID_SEPARATOR));
      if ($field_datasource_id != $datasource_id) {
        unset($fields[$name]);
      }
    }
    return $fields;
  }

}
