<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Highlight.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\String;
use Drupal\Core\Render\Element;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "highlight",
 *   label = @Translation("Highlight processor"),
 *   description = @Translation("Excerpt and field result highlighting")
 * )
 */
class Highlight extends ProcessorPluginBase {

  /**
   * PREG regular expression for a word boundary.
   *
   * We highlight around non-indexable or CJK characters.
   *
   * @var string
   */
  protected static $boundary;

  /**
   * PREG regular expression for splitting words.
   *
   * We highlight around non-indexable or CJK characters.
   *
   * @var string
   */
  protected static $split;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $cjk = '\x{1100}-\x{11FF}\x{3040}-\x{309F}\x{30A1}-\x{318E}' .
        '\x{31A0}-\x{31B7}\x{31F0}-\x{31FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FCF}' .
        '\x{A000}-\x{A48F}\x{A4D0}-\x{A4FD}\x{A960}-\x{A97F}\x{AC00}-\x{D7FF}' .
        '\x{F900}-\x{FAFF}\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}\x{FF66}-\x{FFDC}' .
        '\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}';
    self::$boundary = '(?:(?<=[' . Unicode::PREG_CLASS_WORD_BOUNDARY . $cjk . '])|(?=[' . Unicode::PREG_CLASS_WORD_BOUNDARY . $cjk . ']))';
    self::$split = '/[' . Unicode::PREG_CLASS_WORD_BOUNDARY . $cjk . ']+/iu';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'prefix' => '<strong>',
      'suffix' => '</strong>',
      'excerpt' => TRUE,
      'excerpt_length' => 256,
      'highlight' => 'always',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['prefix'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Highlighting prefix'),
      '#description' => $this->t('Text/HTML that will be prepended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->configuration['prefix'],
    );
    $form['suffix'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Highlighting suffix'),
      '#description' => $this->t('Text/HTML that will be appended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->configuration['suffix'],
    );
    $form['excerpt'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Create excerpt'),
      '#description' => $this->t('When enabled, an excerpt will be created for searches with keywords, containing all occurrences of keywords in a fulltext field.'),
      '#default_value' => $this->configuration['excerpt'],
    );
    $form['excerpt_length'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Excerpt length'),
      '#description' => $this->t('The requested length of the excerpt, in characters.'),
      '#default_value' => $this->configuration['excerpt_length'],
      '#element_validate' => array('form_validate_number'),
      '#min' => 1,
      '#states' => array(
        'visible' => array(
          // @todo This shouldn't be dependent on the form array structure.
          '#edit-processors-search-api-highlighting-settings-excerpt' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );
    $form['highlight'] = array(
      '#type' => 'select',
      '#title' => $this->t('Highlight returned field data'),
      '#description' => $this->t('Select whether returned fields should be highlighted.'),
      '#options' => array(
        'always' => $this->t('Always'),
        'server' => $this->t('If the server returns fields'),
        'never' => $this->t('Never'),
      ),
      '#default_value' => $this->configuration['highlight'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $minimum_excerpt_length = 50;
    if ($form_state['values']['excerpt_length'] < $minimum_excerpt_length) {
      $error_message = $this->t('The minimum excerpt length should be higher than !minimum_excerpt_length', array('!minimum_excerpt_length' => $minimum_excerpt_length));
      \Drupal::formBuilder()->setError($form['excerpt_length'], $form_state, $error_message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    if (!$results->getResultCount() || !($keys = $this->getKeywords($results->getQuery()))) {
      return;
    }

    $result_items = $results->getResultItems();
    if ($this->configuration['excerpt']) {
      foreach ($result_items as $id => $result) {
        $this->postprocessExcerptResults($result_items, $id, $keys);
      }
    }
    if ($this->configuration['highlight'] != 'never') {
      $higlighted_fields = $this->postprocessFieldResults($result_items, $keys);
      if ($higlighted_fields) {
        // Maybe the backend or some other processor has already set highlighted
        // field values.
        foreach ($results->getExtraData('highlighted_fields', array()) as $item_id => $old_highlighting) {
          $higlighted_fields += array($item_id => array());
          $higlighted_fields[$item_id] += $old_highlighting;
        }
        $results->setExtraData('highlighted_fields', $higlighted_fields);
      }
    }
  }

  /**
   * For a single result retrieve fields and create a highlighted excerpt.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $results
   *   All results returned by the search.
   * @param string $id
   *   The item ID of the item whose fields should be highlighted.
   * @param array $keys
   *   The search keys to use for highlighting.
   */
  protected function postprocessExcerptResults(array $results, $id, array $keys) {
    $text = array();
    $items = $this->getFulltextFields($results, $id);
    foreach ($items as $item_id => $items) {
      foreach ($items as $field_id => $field) {
        $text = array_merge($text, $field->getValues());
      }
    }
    $results[$id]->setExcerpt($this->createExcerpt(implode("\n\n", $text), $keys));
  }

  /**
   * Retrieves highlighted field values for result items.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $results
   *   All result items returned by the search.
   * @param array $keys
   *   The search keys to use for highlighting.
   *
   * @return string[][][]
   *   An array with either zero or one entries. The possible entry has a key of
   *   $id and maps field IDs to the highlighted versions of the values for that
   *   field.
   */
  protected function postprocessFieldResults(array $results, array $keys) {
    $items = $this->getFulltextFields($results, $this->configuration['highlight'] == 'always');
    $highlighted_fields = array();
    foreach ($items as $item_id => $fields) {
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($fields as $field_id => $field) {
        $values = $field->getValues();
        $change = FALSE;
        foreach ($values as $i => $value) {
          $values[$i] = $this->highlightField($value, $keys);
          if ($values[$i] !== $value) {
            $change = TRUE;
          }
        }
        if ($change) {
          $highlighted_fields[$item_id][$field_id] = $values;
        }
      }
    }
    return $highlighted_fields;
  }

  /**
   * Retrieves the fulltext data of a result.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $result_items
   *   All results returned by the search, keyed by item ID.
   * @param bool $load
   *   TRUE if the item should be loaded if necessary, FALSE if only fields
   *   already returned in the results should be used.
   *
   * @return \Drupal\search_api\Item\FieldInterface[][]
   *   Fields of the item for which data should be extracted.
   */
  protected function getFulltextFields(array $result_items, $load = TRUE) {
    $items = array();

    // All the index's fulltext fields, grouped by datasource.
    $fulltext_fields = array();
    foreach ($this->index->getFields() as $field_id => $field) {
      if (Utility::isTextType($field->getType())) {
        $fulltext_fields[$field->getDatasourceId()][$field_id] = $field;
      }
    }

    $needs_extraction = array();
    foreach ($result_items as $item_id => $result_item) {
      $datasource_id = $result_item->getDatasourceId();
      /** @var \Drupal\search_api\Item\FieldInterface $field */
      foreach ($fulltext_fields[$datasource_id] as $field_id => $field) {
        if ($result_item->getField($field_id, FALSE)) {
          $items[$item_id][$field_id] = $result_item->getField($field_id, FALSE);
        }
        elseif ($load) {
          $needs_extraction[$item_id][$field->getPropertyPath()] = clone $field;
        }
      }
    }

    $needs_load = array();
    foreach ($needs_extraction as $item_id => $fields) {
      if (!$result_items[$item_id]->getOriginalObject(FALSE)) {
        $needs_load[$item_id] = $item_id;
      }
    }

    if ($needs_load) {
      foreach ($this->index->loadItemsMultiple($needs_load) as $item_id => $object) {
        $result_items[$item_id]->setOriginalObject($object);
        unset($needs_load[$item_id]);
      }
    }

    // Remove the fields for all items that couldn't be loaded.
    $needs_extraction = array_diff_key($needs_extraction, $needs_load);

    foreach ($needs_extraction as $item_id => $fields) {
      Utility::extractFields($result_items[$item_id]->getOriginalObject(), $fields);
      foreach ($fields as $field) {
        $field_id = $field->getFieldIdentifier();
        $result_items[$item_id]->setField($field_id, $field);
        $items[$item_id][$field_id] = $field;
      }
    }

    return $items;
  }

  /**
   * Extracts the positive keywords used in a search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query from which to extract the keywords.
   *
   * @return array
   *   An array of all unique positive keywords used in the query.
   */
  protected function getKeywords(QueryInterface $query) {
    $keys = $query->getKeys();
    if (!$keys) {
      return array();
    }
    if (is_array($keys)) {
      return $this->flattenKeysArray($keys);
    }

    $keywords_in = preg_split(self::$split, $keys);
    // Assure there are no duplicates. (This is actually faster than
    // array_unique() by a factor of 3 to 4.)
    // Remove quotes from keywords.
    $keywords = array();
    foreach (array_filter($keywords_in) as $keyword) {
      if ($keyword = trim($keyword, "'\"")) {
        $keywords[$keyword] = $keyword;
      }
    }
    return $keywords;
  }

  /**
   * Extracts the positive keywords from a keys array.
   *
   * @param array $keys
   *   A search keys array, as specified by SearchApiQueryInterface::getKeys().
   *
   * @return array
   *   An array of all unique positive keywords contained in the keys.
   */
  protected function flattenKeysArray(array $keys) {
    if (!empty($keys['#negation'])) {
      return array();
    }

    $keywords = array();
    foreach ($keys as $i => $key) {
      if (!Element::child($i)) {
        continue;
      }
      if (is_array($key)) {
        $keywords += $this->flattenKeysArray($key);
      }
      else {
        $keywords[$key] = $key;
      }
    }

    return $keywords;
  }

  /**
   * Returns snippets from a piece of text, with certain keywords highlighted.
   *
   * Largely copied from search_excerpt().
   *
   * @param string $text
   *   The text to extract fragments from.
   * @param array $keys
   *   Search keywords entered by the user.
   *
   * @return string
   *   A string containing HTML for the excerpt.
   */
  // @todo Update to new D8 search_excerpt().
  protected function createExcerpt($text, array $keys) {
    // Prepare text by stripping HTML tags and decoding HTML entities.
    $text = strip_tags(str_replace(array('<', '>'), array(' <', '> '), $text));
    $text = ' ' . String::decodeEntities($text);
    // Extract fragments of about 60 characters around keywords, bounded by word
    // boundary characters. Try to reach 256 characters, using second
    // occurrences if necessary.
    $ranges = array();
    $length = 0;
    $look_start = array();
    $remaining_keys = $keys;

    // Get the set excerpt length from the configuration
    $excerpt_length = $this->configuration['excerpt_length'];
    $context_length = (round($excerpt_length / 4) - 5);

    while ($length < $excerpt_length && !empty($remaining_keys)) {
      $found_keys = array();
      foreach ($remaining_keys as $key) {
        if ($length >= $excerpt_length) {
          break;
        }

        // Remember where we last found $key, in case we are coming through a
        // second time.
        if (!isset($look_start[$key])) {
          $look_start[$key] = 0;
        }

        // See if we can find $key after where we found it the last time. Since
        // we are requiring a match on a word boundary, make sure $text starts
        // and ends with a space.
        $matches = array();
        if (preg_match('/' . self::$boundary . $key . self::$boundary . '/iu', ' ' . $text . ' ', $matches, PREG_OFFSET_CAPTURE, $look_start[$key])) {
          $found_position = $matches[0][1];
          $look_start[$key] = $found_position + 1;
          // Keep track of which keys we found this time, in case we need to
          // pass through again to find more text.
          $found_keys[] = $key;

          // Locate a space before and after this match, leaving context on each end.
          $before = strpos(' ' . $text, ' ', max(0, $found_position - $context_length));
          if ($before !== FALSE && $before <= $found_position) {
            $after = strpos($text . ' ', ' ', min($found_position + $context_length, strlen($text)));
            if ($after !== FALSE && $after > $found_position) {
              // Account for the spaces we added.
              $before = max($before - 1, 0);
              $after = min($after, strlen($text));
              if ($before < $after) {
                // Save this range.
                $ranges[$before] = $after;
                $length += $after - $before;
              }
            }
          }
        }
      }
      // Next time through this loop, only look for keys we found this time,
      // if any.
      $remaining_keys = $found_keys;
    }

    if (empty($ranges)) {
      // We didn't find any keyword matches, return NULL.
      return NULL;
    }

    // Sort the text ranges by starting position.
    ksort($ranges);

    // Collapse overlapping text ranges into one. The sorting makes it O(n).
    $new_ranges = array();
    $max_end = 0;
    foreach ($ranges as $this_from => $this_to) {
      $max_end = max($max_end, $this_to);
      if (!isset($working_from)) {
        // This is the first time through this loop: initialize.
        $working_from = $this_from;
        $working_to = $this_to;
        continue;
      }
      if ($this_from <= $working_to) {
        // The ranges overlap: combine them.
        $working_to = max($working_to, $this_to);
      }
      else {
        // The ranges do not overlap save the working range and start a new one.
        $new_ranges[$working_from] = $working_to;
        $working_from = $this_from;
        $working_to = $this_to;
      }
    }
    // Save the remaining working range.
    $new_ranges[$working_from] = $working_to;

    // Fetch text within the combined ranges we found.
    $out = array();
    foreach ($new_ranges as $from => $to) {
      $out[] = substr($text, $from, $to - $from);
    }

    // Combine the text chunks with "..." separators. The "..." needs to be
    // translated. Let translators have the ... separator text as one chunk.
    $dots = explode('!excerpt', $this->t('... !excerpt ... !excerpt ...'));
    $text = (isset($new_ranges[0]) ? '' : $dots[0]) . implode($dots[1], $out) . (($max_end < strlen($text) - 1) ? $dots[2] : '');
    $text = String::checkPlain($text);

    return $this->highlightField($text, $keys);
  }

  /**
   * Marks occurrences of the search keywords in a text field.
   *
   * @param string $text
   *   The text of the field.
   * @param array $keys
   *   Search keywords entered by the user.
   *
   * @return string
   *   The field's text with all occurrences of search keywords highlighted.
   */
  protected function highlightField($text, array $keys) {
    $replace = $this->configuration['prefix'] . '\0' . $this->configuration['suffix'];
    $keys = implode('|', array_map('preg_quote', $keys, array_fill(0, count($keys), '/')));
    $text = preg_replace('/' . self::$boundary . '(' . $keys . ')' . self::$boundary . '/iu', $replace, ' ' . $text . ' ');
    return trim($text);
  }

}
