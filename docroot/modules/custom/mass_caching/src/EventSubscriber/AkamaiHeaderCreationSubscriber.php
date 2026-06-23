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
   * Hashes cache tags before Akamai writes them to the response header.
   *
   * @param \Drupal\akamai\Event\AkamaiHeaderEvents $event
   *   The Akamai header creation event.
   */
  public function hashTags(AkamaiHeaderEvents $event): void {
    $event->data = $this->hashFormattedTags($event->data);
  }

  /**
   * Hashes cache tags before Akamai invalidation requests are sent.
   *
   * @param \Drupal\akamai\Event\AkamaiPurgeEvents $event
   *   The Akamai purge creation event.
   */
  public function hashPurgeTags(AkamaiPurgeEvents $event): void {
    $event->data = $this->hashFormattedTags($event->data);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['onHeaderCreation', -100];
    $events[AkamaiHeaderEvents::HEADER_CREATION][] = ['hashTags', -1000];
    $events[AkamaiPurgeEvents::PURGE_CREATION][] = ['hashPurgeTags', -1000];
    return $events;
  }

  /**
   * Formats tags the same way Akamai does before hashing them.
   *
   * @param array $tags
   *   The cache tags to format and hash.
   *
   * @return array
   *   The formatted cache tag hashes.
   */
  private function hashFormattedTags(array $tags): array {
    $tags = array_map([$this->tagFormatter, 'format'], $tags);
    return Hash::cacheTags($tags);
  }

}
