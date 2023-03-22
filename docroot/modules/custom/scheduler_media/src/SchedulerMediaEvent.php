<?php

namespace Drupal\scheduler_media;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wraps a scheduler event for event listeners.
 */
class SchedulerMediaEvent extends Event {

  /**
   * Entity object.
   *
   * @var Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs a scheduler event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node object that caused the event to fire.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Gets entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node object that caused the event to fire.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Sets the entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node object that caused the event to fire.
   */
  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
  }

}
