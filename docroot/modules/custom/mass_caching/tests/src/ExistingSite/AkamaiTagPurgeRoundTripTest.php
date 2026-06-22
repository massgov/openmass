<?php

namespace Drupal\Tests\mass_caching\ExistingSite;

use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\mass_caching\AkamaiTagPurger;
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
 * This compares both REAL code paths for the same source cache tag:
 *  - Header path mirrors \Drupal\akamai\EventSubscriber\CacheableResponseSubscriber::onRespond():
 *    dispatch HEADER_CREATION (AkamaiHeaderCreationSubscriber hashes the tags),
 *    then format each tag.
 *  - Purge path uses \Drupal\mass_caching\AkamaiTagPurger::hashTag(), the hashing
 *    its invalidate() applies to each raw tag before sending it to Akamai.
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

      // Purge path: the hashed tag AkamaiTagPurger::invalidate() sends to Akamai.
      $purge_tags = [AkamaiTagPurger::hashTag($source_tag)];

      $this->assertSame(
        $header_tags,
        $purge_tags,
        sprintf(
          'Cache tag "%s": the Edge-Cache-Tag header stores "%s" and a tag purge sends "%s"; '
          . 'Akamai matches tags as exact strings, so these must be identical.',
          $source_tag,
          implode(',', $header_tags),
          implode(',', $purge_tags)
        )
      );
    }
  }

}
