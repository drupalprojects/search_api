<?php

/**
 * @file
 * Contains \Drupal\search_api\Form\IndexStatusForm.
 */

namespace Drupal\search_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\search_api\Batch\IndexBatchHelper;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;

/**
 * Form which allows basic operation on an index, e.g. clear indexed data.
 */
class IndexStatusForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index_status';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, IndexInterface $index = NULL) {
    // Attach the search index to the form.
    $form['#index'] = $index;

    // Attach the admin css.
    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    // Check if the index has a valid tracker available.
    if ($index->hasValidTracker()) {
      // Build the index now option.
      $form['index'] = array(
        '#type' => 'details',
        '#title' => $this->t('Index now'),
        '#open' => TRUE,
        '#attributes' => array(
          'class' => array('container-inline'),
        ),
      );
      // Determine whether the index has remaining items to index.
      $has_remaining_items = ($index->getTracker()->getRemainingItemsCount() > 0);
      // Get the value which represent indexing all remaining items.
      $all_value = $this->t('all', array(), array('context' => 'items to index'));
      // Build the number of batches to execute.
      $limit = array(
        '#type' => 'textfield',
        '#default_value' => $all_value,
        '#size' => 4,
        '#attributes' => array(
          'class' => array('search-api-limit'),
        ),
        '#disabled' => !$has_remaining_items,
      );
      // Build the batch size.
      $batch_size = array(
        '#type' => 'textfield',
        '#default_value' => $index->getOption('cron_limit', \Drupal::configFactory()->get('search_api.settings')->get('cron_limit')),
        '#size' => 4,
        '#attributes' => array(
          'class' => array('search-api-batch-size'),
        ),
        '#disabled' => !$has_remaining_items,
      );
      // Here it gets complicated. We want to build a sentence from the form
      // input elements, but to translate that we have to make the two form
      // elements (for limit and batch size) pseudo-variables in the $this->t()
      // call.
      // Since we can't pass them directly, we split the translated sentence
      // (which still has the two tokens), figure out their order and then put
      // the pieces together again using the form elements' #prefix and #suffix
      // properties.
      $sentence = preg_split('/@(limit|batch_size)/', $this->t('Index @limit items in batches of @batch_size items'), -1, PREG_SPLIT_DELIM_CAPTURE);
      // Check if the sentence contains the expected amount of parts.
      if (count($sentence) === 5) {
        $first = $sentence[1];
        $form['index'][$first] = ${$first};
        $form['index'][$first]['#prefix'] = $sentence[0];
        $form['index'][$first]['#suffix'] = $sentence[2];
        $second = $sentence[3];
        $form['index'][$second] = ${$second};
        $form['index'][$second]['#suffix'] = "{$sentence[4]} ";
      }
      else {
        // Sentence is broken. Use fallback method instead.
        $limit['#title'] = $this->t('Number of items to index');
        $form['index']['limit'] = $limit;
        $batch_size['#title'] = $this->t('Number of items per batch run');
        $form['index']['batch_size'] = $batch_size;
      }
      // Add the value "all" so it can be used by the validation.
      $form['index']['all'] = array(
        '#type' => 'value',
        '#value' => $all_value,
      );
      // Build the index now action.
      $form['index']['index_now'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Index now'),
        '#disabled' => !$has_remaining_items,
        '#name' => 'index_now',
      );
      // Build the index manipulation actions.
      $form['actions']['#type'] = 'actions';
      $form['actions']['reindex'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Queue all items for reindexing'),
        '#name' => 'reindex',
        '#button_type' => 'danger',
      );
      $form['actions']['clear'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Clear all indexed data'),
        '#name' => 'clear',
        '#button_type' => 'danger',
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    // Perform default form validation.
    parent::validateForm($form, $form_state);
    // Check if the user wants to perform "index now" action.
    if ($form_state['triggering_element']['#name'] === 'index_now') {
      // Get the form values.
      $form_values = &$form_state['values'];
      // Get the value for indexing all remaining items and convert to lower
      // case.
      $all_value = drupal_strtolower($form_values['all']);
      // Iterate through the user input fields.
      foreach (array('limit', 'batch_size') as $field) {
        // Get the input value and trim any leading or trailing spaces. Convert
        // value to lower case to ensure all values have the same casing.
        $value = drupal_strtolower(trim($form_state['values'][$field]));
        // Check if all remaining items should be index.
        if ($value === $all_value) {
          // Use the value '-1' instead.
          $value = -1;
        }
        // Check if the value is empty or not numeric.
        elseif (!$value || !is_numeric($value) || ((int) $value) != $value) {
          // Raise form error: Value must be numeric or equal to all.
          $this->setFormError($field, $form_state, $this->t('Enter a non-zero integer. Use "-1" or "@all" for "all items".', array('@all' => $all_value)));
        }
        else {
          // Ensure the value contains an integer value.
          $value = (int) $value;
        }
        // Overwrite the form state value.
        $form_values[$field] = $value;
      }
    }
  }

  /**
   * {@inhertidoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // Get the search index from the form.
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = $form['#index'];
    // Evaluate the triggering element name.
    switch ($form_state['triggering_element']['#name']) {
      case 'index_now':
        // Get the form state values.
        $form_values = $form_state['values'];
        // Try to create a batch job to index items.
        try {
          IndexBatchHelper::create($index, $form_values['batch_size'], $form_values['limit']);
        }
        catch (SearchApiException $e) {
          // Notify user about failure to scheduling the batch job.
          drupal_set_message($this->t('Failed to create a batch, please check the batch size and limit.'), 'warning');
        }
        break;

      case 'reindex':
        // Redirect to the index reindex page.
        $form_state['redirect_route'] = array(
          'route_name' => 'search_api.index_reindex',
          'route_parameters' => array(
            'search_api_index' => $index->id(),
          ),
        );
        break;

      case 'clear':
        // Redirect to the index clear page.
        $form_state['redirect_route'] = array(
          'route_name' => 'search_api.index_clear',
          'route_parameters' => array(
            'search_api_index' => $index->id(),
          ),
        );
        break;
    }
  }

}
