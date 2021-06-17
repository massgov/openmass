<?php


namespace Drupal\mass_scheduled_transitions\EventSubscriber;


use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Drupal\scheduled_transitions\EventSubscriber\ScheduledTransitionsNewRevision;

class MassScheduledTransitionsNewRevision extends ScheduledTransitionsNewRevision {

  /**
   * Set revision owner to be same as the Transition owner. DP-22082.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   */
  public function latestRevision(ScheduledTransitionsNewRevisionEvent $event): void {
    parent::latestRevision($event);
    $transition = $event->getScheduledTransition();
    /** @var \Drupal\Core\Entity\RevisionableInterface $revision */
    $revision = $event->getNewRevision();
    $revision->setRevisionUser($transition->getAuthor());
    $event->setNewRevision($revision);
  }

}