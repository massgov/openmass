<?php

namespace Drupal\mass_caching\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\akamai\Helper\CacheTagFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters Akamai header cache tags before the header is created.
 */
class AkamaiHeaderCreationSubscriber implements EventSubscriberInterface {

  /**
   * Akamai Edge-Cache-Tag response header tag count limit.
   */
  private const MAX_HEADER_TAGS = 128;

  /**
   * Akamai cache tag formatter.
   *
   * @var \Drupal\akamai\Helper\CacheTagFormatter
   */
  private CacheTagFormatter $tagFormatter;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Constructs a new AkamaiHeaderCreationSubscriber.
   *
   * @param \Drupal\akamai\Helper\CacheTagFormatter $tag_formatter
   *   Akamai cache tag formatter.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(CacheTagFormatter $tag_formatter, LoggerInterface $logger) {
    $this->tagFormatter = $tag_formatter;
    $this->logger = $logger;
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
    if (count($event->data) > self::MAX_HEADER_TAGS) {
      $this->logger->warning(
        'Akamai Edge-Cache-Tag header contains {count} tags, exceeding Akamai\'s {limit}-tag header limit. Akamai may reject or truncate the header.',
        [
          'count' => count($event->data),
          'limit' => self::MAX_HEADER_TAGS,
        ]
      );
    }
  }

  /**
   * Hashes cache tags before Akamai uses them in headers or purges.
   *
   * @param \Drupal\akamai\Event\AkamaiHeaderEvents|\Drupal\akamai\Event\AkamaiPurgeEvents $event
   *   The Akamai event containing cache tags.
   */
  public function hashTags(AkamaiHeaderEvents|AkamaiPurgeEvents $event): void {
    if ($event instanceof AkamaiPurgeEvents && $this->containsUrlPurgePayload($event->data)) {
      return;
    }

    $tags = array_map([$this->tagFormatter, 'format'], $event->data);
    $event->data = Hash::cacheTags($tags);
  }

  /**
   * Determines whether an Akamai purge event payload contains URLs or paths.
   *
   * AkamaiPurgeEvents is shared by tag and URL purgers, but URL payloads must
   * not be processed as cache tags.
   *
   * @param array $payload
   *   The purge event payload.
   *
   * @return bool
   *   TRUE when the payload contains URL/path invalidations.
   */
  private function containsUrlPurgePayload(array $payload): bool {
    foreach ($payload as $item) {
      if (!is_string($item)) {
        continue;
      }
      if (str_starts_with($item, '/') || preg_match('@^[a-z][a-z0-9+.-]*://@i', $item)) {
        return TRUE;
      }
    }
    return FALSE;
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
