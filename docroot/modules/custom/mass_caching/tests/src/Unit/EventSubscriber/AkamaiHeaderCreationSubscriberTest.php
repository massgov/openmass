<?php

namespace Drupal\Tests\mass_caching\Unit\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\akamai\Helper\CacheTagFormatter;
use Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger;
use Drupal\mass_caching\EventSubscriber\AkamaiHeaderCreationSubscriber;
use Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests Akamai header creation event alterations.
 *
 * @group mass_caching
 */
class AkamaiHeaderCreationSubscriberTest extends UnitTestCase {

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
    $subscriber = $this->createSubscriber();

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
    $subscriber = $this->createSubscriber();

    $subscriber->hashTags($event);

    $this->assertSame(Hash::cacheTags([
      'node_list_news',
      'node_123',
    ]), $event->data);
  }

  /**
   * Tests header tag hashes match Akamai tag invalidation hashes.
   */
  public function testHeaderTagHashesMatchPurgeTagHashes(): void {
    $tags = [
      'node_list:news',
      'node:123',
      'config:system.site',
    ];
    $subscriber = $this->createSubscriber();

    $header_event = new AkamaiHeaderEvents($tags);
    $subscriber->hashTags($header_event);

    $purged_tags = NULL;
    // The helper wires a mocked Akamai client whose purgeTags() expectation
    // assigns the outbound tag payload to $purged_tags.
    $purger = $this->createAkamaiTagPurgerCapturingPurgeTags($subscriber, $purged_tags);
    $invalidations = $this->createTagInvalidations($tags);

    // Drive the real Akamai tag purger so this covers Akamai's formatting and
    // purge event order before the tags are handed to the mocked Akamai client.
    $purger->invalidate($invalidations);

    $this->assertSame($header_event->data, $purged_tags);
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
      AkamaiPurgeEvents::PURGE_CREATION => [
        ['hashTags', -1000],
      ],
    ], AkamaiHeaderCreationSubscriber::getSubscribedEvents());
  }

  /**
   * Creates the subscriber under test.
   */
  private function createSubscriber(): AkamaiHeaderCreationSubscriber {
    return new AkamaiHeaderCreationSubscriber(new CacheTagFormatter());
  }

  /**
   * Creates an Akamai tag purger that captures its outbound tag payload.
   */
  private function createAkamaiTagPurgerCapturingPurgeTags(AkamaiHeaderCreationSubscriber $subscriber, ?array &$purged_tags): AkamaiTagPurger {
    $purger = $this->getMockBuilder(AkamaiTagPurger::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    $container = new ContainerBuilder();
    $container->set('akamai.helper.cachetagformatter', new CacheTagFormatter());
    \Drupal::setContainer($container);

    $client = $this->getMockBuilder('Drupal\akamai\Plugin\Client\AkamaiClientV3')
      ->disableOriginalConstructor()
      ->onlyMethods(['setType', 'purgeTags'])
      ->getMock();
    $client->expects($this->once())
      ->method('setType')
      ->with('tag');
    $client->expects($this->once())
      ->method('purgeTags')
      ->with($this->callback(function (array $tags) use (&$purged_tags): bool {
        $purged_tags = $tags;
        return TRUE;
      }))
      ->willReturn(TRUE);

    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addSubscriber($subscriber);

    $this->setPurgerProperty($purger, 'client', $client);
    $this->setPurgerProperty($purger, 'eventDispatcher', $event_dispatcher);

    return $purger;
  }

  /**
   * Creates tag invalidations with the given expressions.
   */
  private function createTagInvalidations(array $tags): array {
    $invalidations = [];
    foreach ($tags as $tag) {
      $invalidation = $this->getMockBuilder(TagInvalidation::class)
        ->disableOriginalConstructor()
        ->getMock();
      $invalidation->method('getExpression')
        ->willReturn($tag);
      $invalidations[] = $invalidation;
    }
    return $invalidations;
  }

  /**
   * Sets a protected dependency on the contrib Akamai purger.
   */
  private function setPurgerProperty(AkamaiTagPurger $purger, string $property, mixed $value): void {
    $reflection = new \ReflectionClass($purger);
    $reflection_property = $reflection->getProperty($property);
    $reflection_property->setAccessible(TRUE);
    $reflection_property->setValue($purger, $value);
  }

}
