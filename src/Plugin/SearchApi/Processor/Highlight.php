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
use Drupal\search_api\Utility\Utility;

/**
 * @SearchApiProcessor(
 *   id = "search_api_highlight_processor",
 *   label = @Translation("Hightlight processor"),
 *   description = @Translation("Excerpt and field result hightlighting")
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
      '#title' => t('Highlighting prefix'),
      '#description' => t('Text/HTML that will be prepended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->configuration['prefix'],
    );
    $form['suffix'] = array(
      '#type' => 'textfield',
      '#title' => t('Highlighting suffix'),
      '#description' => t('Text/HTML that will be appended to all occurrences of search keywords in highlighted text.'),
      '#default_value' => $this->configuration['suffix'],
    );
    $form['excerpt'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create excerpt'),
      '#description' => t('When enabled, an excerpt will be created for searches with keywords, containing all occurrences of keywords in a fulltext field.'),
      '#default_value' => $this->configuration['excerpt'],
    );
    $form['excerpt_length'] = array(
      '#type' => 'textfield',
      '#title' => t('Excerpt length'),
      '#description' => t('The requested length of the excerpt, in characters.'),
      '#default_value' => $this->configuration['excerpt_length'],
      '#element_validate' => array('form_validate_number'),
      '#min' => 1,
      '#states' => array(
        'visible' => array(
          '#edit-processors-search-api-highlighting-settings-excerpt' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );
    $form['highlight'] = array(
      '#type' => 'select',
      '#title' => t('Highlight returned field data'),
      '#description' => t('Select whether returned fields should be highlighted.'),
      '#options' => array(
        'always' => t('Always'),
        'server' => t('If the server returns fields'),
        'never' => t('Never'),
      ),
      '#default_value' => $this->configuration['highlight'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    // Overridden so $form['fields'] is not checked.
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(array &$response, QueryInterface $query) {
    if (!$response['result count'] || !($keys = $this->getKeywords($query))) {
      return;
    }

    foreach ($response['results'] as $id => $result) {
      if ($this->configuration['excerpt']) {
        $this->postprocessExcerptResults($response['results'], $id, $keys);
      }
      if ($this->configuration['highlight'] != 'never') {
        $this->postprocessFieldResults($response['results'], $id, $keys);
      }
    }
  }

  /**
   * For a single result retrieve fields and create an highlighted excerpt.
   */
  protected function postprocessExcerptResults(array &$results, $id, $keys) {
    $text = array();
    $fields = $this->getFulltextFields($results, $id);
    foreach ($fields as $data) {
      $text = array_merge($text, $data);
    }
    $result['excerpt'] = $this->createExcerpt(implode("\n\n", $text), $keys);
  }

  /**
   * For a single result retrieve fields and highlight.
   */
  protected function postprocessFieldResults(array &$results, $id, $keys) {
    $fields = $this->getFulltextFields($results, $id, $this->configuration['highlight'] == 'always');
    foreach ($fields as $field => $data) {
      foreach ($data as $i => $text) {
        $results['fields'][$field][$i] = $this->highlightField($text, $keys);
      }
    }
  }

  /**
   * Retrieves the fulltext data of a result.
   *
   * @param array $results
   *   All results returned in the search, by reference.
   * @param int|string $i
   *   The index in the results array of the result whose data should be
   *   returned.
   * @param bool $load
   *   TRUE if the item should be loaded if necessary, FALSE if only fields
   *   already returned in the results should be used.
   *
   * @return array
   *   An array containing fulltext field names mapped to the text data
   *   contained in them for the given result.
   */
  protected function getFulltextFields(array &$results, $i, $load = TRUE) {
    $data = array();

    $result = &$results[$i];
    // Act as if $load is TRUE if we have a loaded item.
    $load |= !empty($result['entity']);
    $result += array('fields' => array());
    $fulltext_fields = $this->index->getFulltextFields();
    // We only need detailed fields data if $load is TRUE.
    $fields = $load ? $this->index->getFields() : array();
    $needs_extraction = array();
    foreach ($fulltext_fields as $field) {
      if (array_key_exists($field, $result['fields'])) {
        $data[$field] = $result['fields'][$field];
      }
      elseif ($load) {
        $needs_extraction[$field] = $fields[$field];
      }
    }

    if (!$needs_extraction) {
      return $data;
    }

    if (empty($result['entity'])) {
      $this->loadResultsEntities($results);
    }
    // If we still don't have a loaded item, we should stop trying.
    if (empty($result['entity'])) {
      return $data;
    }

    Utility::extractFields($result['entity'], $needs_extraction);

    foreach ($needs_extraction as $field => $info) {
      if (isset($info['value'])) {
        $data[$field] = $info['value'];
      }
    }

    return $data;
  }

  /**
   * Loads entities into results array.
   */
  protected function loadResultsEntities(array &$results) {
    $items = $this->index->loadItems(array_keys($results));
    foreach ($items as $id => $item) {
      $results[$id]['entity'] = $item;
    }
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

    while ($length < 256 && !empty($remaining_keys)) {
      $found_keys = array();
      foreach ($remaining_keys as $key) {
        if ($length >= 256) {
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

          // Locate a space before and after this match, leaving about 60
          // characters of context on each end.
          $before = strpos(' ' . $text, ' ', max(0, $found_position - 61));
          if ($before !== FALSE && $before <= $found_position) {
            $after = strpos($text . ' ', ' ', min($found_position + 61, strlen($text)));
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
    return trim(substr($text, 1, -1));
  }

}
