<?php

namespace Drupal\mass_caching\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\akamai\Helper\CacheTagFormatter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters Akamai header cache tags before the header is created.
 */
class AkamaiHeaderCreationSubscriber implements EventSubscriberInterface {

  /**
   * Akamai cache tag formatter.
   *
   * @var \Drupal\akamai\Helper\CacheTagFormatter
   */
  private CacheTagFormatter $tagFormatter;

  /**
   * Constructs a new AkamaiHeaderCreationSubscriber.
   *
   * @param \Drupal\akamai\Helper\CacheTagFormatter $tag_formatter
   *   Akamai cache tag formatter.
   */
  public function __construct(CacheTagFormatter $tag_formatter) {
    $this->tagFormatter = $tag_formatter;
  }

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
   * Hashes cache tags before Akamai uses them in headers or purges.
   *
   * @param \Drupal\akamai\Event\AkamaiHeaderEvents|\Drupal\akamai\Event\AkamaiPurgeEvents $event
   *   The Akamai event containing cache tags.
   */
  public function hashTags(AkamaiHeaderEvents|AkamaiPurgeEvents $event): void {
    $tags = array_map([$this->tagFormatter, 'format'], $event->data);
    $event->data = Hash::cacheTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['onHeaderCreation', -100];
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['hashTags', -1000];
    $events[AkamaiPurgeEvents::PURGE_CREATION][] = ['hashTags', -1000];
    return $events;
  }

}
