<?php
// $Id$

/**
 * Search API data alteration callback that adds an URL field for all items.
 */
class SearchApiAlterAddFulltext extends SearchApiAbstractAlterCallback {

  public function alterItems(array &$items) {
    if (empty($this->index->options['fields'])) {
      $fields = FALSE;
    }
    else {
      $fields = array_intersect_key($this->index->options['fields'], drupal_map_assoc($this->index->getFulltextFields()));
    }
    foreach ($items as $id => &$item) {
      $wrapper = entity_metadata_wrapper($this->index->entity_type, $item);
      $text = '';
      if ($fields) {
        foreach (array_keys($fields) as $field) {
          try {
            $tmp = $wrapper;
            foreach (explode(':', $field) as $property) {
              if (!isset($tmp->$property)) {
                // This can especially happen for fields added by data alter callbacks – e.g., this one.
                $tmp = FALSE;
                break;
              }
              $tmp = $tmp->$property;
            }
            if ($tmp) {
              $text .= $tmp->value() . "\n\n";
            }
          }
          catch (Exception $e) {
            // This might happen for entity fields that are NULL, e.g. comments without parent.
            // We just ignore the field, then.
          }
        }
      }
      else {
        foreach ($wrapper as $key => $field) {
          $info = $field->info();
          if ($info['type'] == 'text') {
            $text .= $field->value() . "\n\n";
          }
        }
      }
      $item->search_api_fulltext = $text;
    }
  }

  public function propertyInfo() {
    return array(
      'search_api_fulltext' => array(
        'label' => t('Fulltext'),
        'description' => t('The aggregated content of all fulltext fields.'),
        'type' => 'text',
      ),
    );
  }

}
