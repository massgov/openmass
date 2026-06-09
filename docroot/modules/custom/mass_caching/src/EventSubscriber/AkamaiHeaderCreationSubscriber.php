<?php

namespace Drupal\mass_caching\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters Akamai header cache tags before the header is created.
 */
class AkamaiHeaderCreationSubscriber implements EventSubscriberInterface {

  /**
   * Removes the unscoped node list cache tag from Akamai header tags.
   *
   * @param \Drupal\akamai\Event\AkamaiHeaderEvents $event
   *   The Akamai header creation event.
   */
  public function onHeaderCreation(AkamaiHeaderEvents $event): void {
    $event->data = array_values(array_filter($event->data, static function ($tag): bool {
      return $tag !== 'node_list';
    }));
  }

  /**
   * Hashes cache tags before Akamai writes them to the response header.
   *
   * @param \Drupal\akamai\Event\AkamaiHeaderEvents $event
   *   The Akamai header creation event.
   */
  public function hashTags(AkamaiHeaderEvents $event): void {
    $event->data = Hash::cacheTags($event->data);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['onHeaderCreation', -100];
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['hashTags', -1000];
    return $events;
  }

}
