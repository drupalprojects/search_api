<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\row\SearchApiRow.
 */

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic entity row plugin to provide a common base for all entity types.
 *
 * @ViewsRow(
 *   id = "search_api",
 *   title = @Translation("Rendered Search API item"),
 *   help = @Translation("Displays entity of the matching search API item"),
 * )
 */
class SearchApiRow extends RowPluginBase {

  /**
   * The associated views query object.
   *
   * @var \Drupal\search_api\Plugin\views\query\SearchApiQuery
   */
  public $query;

  /**
   * The search index.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  public $index;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  public $entityManager;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $index = $view->storage->get('base_table');
    $id = substr($index, strlen('search_api_index_'));
    $this->index = $this->entityManager->getStorage('search_api_index')->load($id);

  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // @todo How to get a default?
    // $options['view_mode'][$datasource->getPluginId()] = array('default' => 'default');
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    foreach ($this->index->getDatasources() as $datasource) {
      $form['view_mode'][$datasource->getPluginId()] = array(
        '#type' => 'select',
        '#options' => $datasource->getViewModes(),
        '#title' => $this->t('View mode for data type @name', array('@name' => $datasource->getPluginDefinition()['label'])),
        '#default_value' => isset($this->options['view_mode'][$datasource->getPluginId()]) ? $this->options['view_mode'][$datasource->getPluginId()] : 'default',
      );
    }
  }

  /**
   * {@inheritdoc}
   *
  public function summaryTitle() {
    $options = \Drupal::entityManager()->getViewModeOptions($this->entityTypeId);
    if (isset($options[$this->options['view_mode']])) {
      return String::checkPlain($options[$this->options['view_mode']]);
    }
    else {
      return $this->t('No view mode selected');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    try {
      $view_mode = isset($this->options['view_mode'][$row->search_api_datasource]) ? $this->options['view_mode'][$row->search_api_datasource] : 'default';
      return $this->index->getDataSource($row->search_api_datasource)->viewItem($row->_item, $view_mode);
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
      return '';
    }
  }

  public function query() {
    parent::query();
    // @todo Find a better way to ensure that the item is loaded.
    $this->view->query->addField(NULL, '_magic');
  }
}
