<?php
/**
 * @file
 * Contains \Drupal\search_api\Datasource\Entity\EntityItem.
 */

namespace Drupal\search_api\Datasource\Entity;

/*
 * Include required classes and interfaces.
 */
use IteratorAggregate;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Datasource\ItemInterface;

/**
 * Entity datasource item wrapper.
 */
class EntityItem implements IteratorAggregate, ItemInterface {

  /**
   * The wrapped entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  private $entity;

  /**
   * Create an EntityItem object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An instance of EntityInterface.
   */
  public function __construct(EntityInterface $entity) {
    // Setup object members.
    $this->entity = $entity;
  }

  /**
   * Get the wrapped entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An instance of EntityInterface.
   */
  protected function getEntity() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getEntity()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    return $this->getEntity()->get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value, $notify = TRUE) {
    return $this->getEntity()->set($property_name, $value, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    return $this->getEntity()->getProperties($include_computed);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyValues() {
    return $this->getEntity()->getPropertyValues();
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyValues($values) {
    $this->getEntity()->setPropertyValues($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinition($name) {
    return $this->getEntity()->getPropertyDefinition($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return $this->getEntity()->getPropertyDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->getEntity()->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name) {
    return $this->getEntity()->onChange($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition() {
    return $this->getEntity()->getDefinition();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->getEntity()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->getEntity()->setValue($value, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getString() {
    return $this->getEntity()->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    return $this->getEntity()->getConstraints();
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    return $this->getEntity()->validate();
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    return $this->getEntity()->applyDefaultValue($notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->getEntity()->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->getEntity()->getParent();
  }

  /**
   * {@inheritdoc}
   */
  public function getRoot() {
    return $this->getEntity()->getRoot();
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyPath() {
    return $this->getEntity()->getPropertyPath();
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($name = NULL, TypedDataInterface $parent = NULL) {
    $this->getEntity()->setContext($name, $parent);
  }

  /**
   * {@inheritdoc}
   */
  public function language() {
    return $this->getEntity()->language();
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationLanguages($include_default = TRUE) {
    return $this->getEntity()->getTranslationLanguages($include_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation($langcode) {
    return new static($this->getEntity()->getTranslation($langcode));
  }

  /**
   * {@inheritdoc}
   */
  public function getUntranslated() {
    return new static($this->getEntity()->getUntranslated());
  }

  /**
   * {@inheritdoc}
   */
  public function hasTranslation($langcode) {
    return $this->getEntity()->hasTranslation($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function addTranslation($langcode, array $values = array()) {
    return new static($this->getEntity()->addTranslation($langcode, $values));
  }

  /**
   * {@inheritdoc}
   */
  public function removeTranslation($langcode) {
    $this->getEntity()->removeTranslation($langcode);
  }

  /**
   * {@inheritdoc}
   */
	public function getIterator() {
    return $this->getEntity();
  }

}
