<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies the Akamai Edge-Cache-Tag header matches what tag purges send.
 *
 * Akamai purges by exact string match: the tag string written to the
 * Edge-Cache-Tag response header (what Akamai stores against a cached object)
 * must be byte-for-byte identical to the tag string sent in a Fast Purge "tag"
 * request. If they differ, every tag purge silently matches nothing and content
 * stays stale at the edge with no error.
 *
 * This drives both REAL code paths for the same source cache tag, using the
 * site's event_dispatcher (so the registered subscribers actually run):
 *
 *  - Header path mirrors \Drupal\akamai\EventSubscriber\CacheableResponseSubscriber::onRespond():
 *    dispatch HEADER_CREATION (the PR's AkamaiHeaderCreationSubscriber hashes the
 *    tags here), then format each tag.
 *  - Purge path mirrors \Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger::invalidate():
 *    format each tag, then dispatch PURGE_CREATION.
 *
 * @group mass_caching
 */
class AkamaiTagPurgeRoundTripTest extends MassExistingSiteBase {

  /**
   * The Edge-Cache-Tag value and the purged tag value must be identical.
   */
  public function testHeaderTagMatchesPurgeTag(): void {
    $dispatcher = \Drupal::service('event_dispatcher');
    $formatter = \Drupal::service('akamai.helper.cachetagformatter');

    foreach (['node:123', 'rendered', 'media:456'] as $source_tag) {
      // Header path: dispatch HEADER_CREATION (runs the registered
      // AkamaiHeaderCreationSubscriber, which hashes), then format each tag.
      $header_event = new AkamaiHeaderEvents([$source_tag]);
      $dispatcher->dispatch($header_event, AkamaiHeaderEvents::HEADER_CREATION);
      $header_tags = array_values(array_map([$formatter, 'format'], $header_event->data));

      // Purge path: format the raw tag first, then dispatch PURGE_CREATION,
      // exactly as AkamaiTagPurger::invalidate() builds the tags it sends.
      $purge_event = new AkamaiPurgeEvents([$formatter->format($source_tag)]);
      $dispatcher->dispatch($purge_event, AkamaiPurgeEvents::PURGE_CREATION);
      $purge_tags = array_values($purge_event->data);

      $this->assertSame(
        $header_tags,
        $purge_tags,
        sprintf(
          'Cache tag "%s": the Edge-Cache-Tag header stores "%s" but a tag purge sends "%s". '
          . 'Akamai matches tags as exact strings, so this purge would silently clear nothing.',
          $source_tag,
          implode(',', $header_tags),
          implode(',', $purge_tags)
        )
      );
    }
  }

}
