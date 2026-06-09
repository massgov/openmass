<?php

namespace Drupal\Tests\mass_caching\Unit\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\mass_caching\EventSubscriber\AkamaiHeaderCreationSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * Tests Akamai header creation event alterations.
 *
 * @group mass_caching
 */
class AkamaiHeaderCreationSubscriberTest extends TestCase {

  /**
   * Tests the subscriber removes only the exact node_list cache tag.
   */
  public function testNodeListHeaderTagRemoved(): void {
    $event = new AkamaiHeaderEvents([
      'node_list',
      'node_list:news',
      'node:123',
      'node_list',
    ]);
    $subscriber = new AkamaiHeaderCreationSubscriber();

    $subscriber->onHeaderCreation($event);

    $this->assertSame([
      'node_list:news',
      'node:123',
    ], $event->data);
  }

  /**
   * Tests cache tags are hashed after tag alterations.
   */
  public function testHeaderTagsHashed(): void {
    $tags = [
      'node_list:news',
      'node:123',
    ];
    $event = new AkamaiHeaderEvents($tags);
    $subscriber = new AkamaiHeaderCreationSubscriber();

    $subscriber->hashTags($event);

    $this->assertSame(Hash::cacheTags($tags), $event->data);
  }

  /**
   * Tests the subscriber listens to Akamai header creation events.
   */
  public function testSubscribedEvents(): void {
    $this->assertSame([
      AkamaiHeaderEvents::HEADER_CREATION => [
        ['onHeaderCreation', -100],
        ['hashTags', -1000],
      ],
    ], AkamaiHeaderCreationSubscriber::getSubscribedEvents());
  }

}
