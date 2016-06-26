<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty;
use Drupal\search_api\Processor\ProcessorInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\Utility;

/**
 * Adds customized aggregations of existing fields to the index.
 *
 * @SearchApiProcessor(
 *   id = "aggregated_field",
 *   label = @Translation("Aggregated fields"),
 *   description = @Translation("Add customized aggregations of existing fields to the index."),
 *   stages = {
 *     "add_properties" = 20,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AggregatedFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = array();

    if (!$datasource) {
      $definition = array(
        'label' => $this->t('Aggregated field'),
        'description' => $this->t('An aggregation of multiple other fields.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      );
      $properties['aggregated_field'] = new AggregatedFieldProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $aggregated_fields = $this->filterForPropertyPath(
      $this->index->getFieldsByDatasource(NULL),
      'aggregated_field'
    );
    $required_properties_by_datasource = array(
      NULL => array(),
      $item->getDatasourceId() => array(),
    );
    foreach ($aggregated_fields as $field) {
      foreach ($field->getConfiguration()['fields'] as $combined_id) {
        list($datasource_id, $property_path) = Utility::splitCombinedId($combined_id);
        $required_properties_by_datasource[$datasource_id][$property_path] = $combined_id;
      }
    }

    // Extract the required properties.
    $property_values = array();
    /** @var \Drupal\search_api\Item\FieldInterface[][] $missing_fields */
    $missing_fields = array();
    $processor_fields = array();
    $needed_processors = array();
    foreach (array(NULL, $item->getDatasourceId()) as $datasource_id) {
      $properties = $this->index->getPropertyDefinitions($datasource_id);
      foreach ($required_properties_by_datasource[$datasource_id] as $property_path => $combined_id) {
        // If a field with the right property path is already set on the item,
        // use it. This might actually make problems in case the values have
        // already been processed in some way, or use a data type that
        // transformed their original value. But that will hopefully not be a
        // problem in most situations.
        foreach ($this->filterForPropertyPath($item->getFields(FALSE), $property_path) as $field) {
          if ($field->getDatasourceId() === $datasource_id) {
            $property_values[$combined_id] = $field->getValues();
            continue 2;
          }
        }

        // If the field is not already on the item, we need to extract it. We
        // set our own combined ID as the field identifier as kind of a hack,
        // to easily be able to add the field values to $property_values
        // afterwards.
        $property = NULL;
        if (isset($properties[$property_path])) {
          $property = $properties[$property_path];
        }
        if ($property instanceof ProcessorPropertyInterface) {
          $processor_fields[] = Utility::createField($this->index, $combined_id);
          $needed_processors[$property->getProcessorId()] = TRUE;
        }
        elseif ($datasource_id) {
          $missing_fields[$property_path][] = Utility::createField($this->index, $combined_id);
        }
        else {
          // Extracting properties without a datasource is pointless.
          $property_values[$combined_id] = array();
        }
      }
    }
    if ($missing_fields) {
      Utility::extractFields($item->getOriginalObject(), $missing_fields);
      foreach ($missing_fields as $property_fields) {
        foreach ($property_fields as $field) {
          $property_values[$field->getFieldIdentifier()] = $field->getValues();
        }
      }
    }
    if ($processor_fields) {
      $dummy_item = clone $item;
      $dummy_item->setFields($processor_fields);
      $processors = $this->index->getProcessorsByStage(ProcessorInterface::STAGE_ADD_PROPERTIES);
      foreach ($processors as $processor_id => $processor) {
        // Avoid an infinite recursion.
        if (isset($needed_processors[$processor_id]) && $processor != $this) {
          $processor->addFieldValues($dummy_item);
        }
      }
      foreach ($processor_fields as $field) {
        $property_values[$field->getFieldIdentifier()] = $field->getValues();
      }
    }

    $aggregated_fields = $this->filterForPropertyPath($item->getFields(), 'aggregated_field');
    foreach ($aggregated_fields as $aggregated_field) {
      $values = array();
      $configuration = $aggregated_field->getConfiguration();
      foreach ($configuration['fields'] as $combined_id) {
        if (!empty($property_values[$combined_id])) {
          $values = array_merge($values, $property_values[$combined_id]);
        }
      }

      switch ($configuration['type']) {
        case 'concat':
          $values = array(implode("\n\n", $values));
          break;

        case 'sum':
          $values = array(array_sum($values));
          break;

        case 'count':
          $values = array(count($values));
          break;

        case 'max':
          $values = array(max($values));
          break;

        case 'min':
          $values = array(min($values));
          break;

        case 'first':
          if ($values) {
            $values = array(reset($values));
          }
          break;
      }

      $aggregated_field->setValues($values);
    }
  }

}
