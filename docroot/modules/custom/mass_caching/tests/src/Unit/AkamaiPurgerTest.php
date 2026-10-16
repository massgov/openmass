<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\akamai\Helper\CacheTagFormatter;
use Drupal\akamai\Plugin\Client\AkamaiClientV3;
use Drupal\mass_caching\AkamaiPurger;
use Drupal\mass_caching\EventSubscriber\AkamaiHeaderCreationSubscriber;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\PathInvalidation;
use Drupal\purge\Plugin\Purge\Invalidation\UrlInvalidation;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the custom Akamai URL purger.
 */
#[CoversClass(AkamaiPurger::class)]
#[Group('mass_caching')]
class AkamaiPurgerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $url_assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $url_assembler->method('assemble')
      ->willReturnCallback(static fn(string $uri): string => $uri);
    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', $url_assembler);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that repeated request failures are logged and abandoned.
   */
  public function testRepeatedFailuresAreAbandoned(): void {
    $url = 'https://www.mass.gov/node/123';
    $client = $this->createClient();
    $client->expects($this->exactly(AkamaiPurger::MAX_ATTEMPTS))
      ->method('purgeRequest')
      ->with([$url])
      ->willReturn(FALSE);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Akamai @type purge failed after @attempts attempts for %item. Removing the invalidation from the queue.',
        [
          '@type' => 'URL',
          '@attempts' => AkamaiPurger::MAX_ATTEMPTS,
          '%item' => $url,
        ]
      );

    $purger = $this->createPurger($client, logger: $logger);

    $invalidation = $this->createUrlInvalidation(1, $url);

    for ($attempt = 1; $attempt <= AkamaiPurger::MAX_ATTEMPTS; $attempt++) {
      $purger->invalidate([$invalidation]);

      $this->assertSame($attempt, $invalidation->getProperty('attempts'));
      $expected_state = $attempt === AkamaiPurger::MAX_ATTEMPTS
        ? InvalidationInterface::SUCCEEDED
        : InvalidationInterface::FAILED;
      $this->assertSame($expected_state, $invalidation->getState());
    }
  }

  /**
   * Tests that a failed chunk does not affect successful chunks.
   */
  public function testFailureAttemptsAreLimitedToFailedChunk(): void {
    $urls = [];
    $invalidations = [];
    for ($id = 1; $id <= 7; $id++) {
      $urls[] = "https://www.mass.gov/node/$id";
      $invalidations[] = $this->createUrlInvalidation($id, "https://www.mass.gov/node/$id");
    }

    $request = 0;
    $client = $this->createClient();
    $client->expects($this->exactly(2))
      ->method('purgeRequest')
      ->willReturnCallback(function (array $chunk) use (&$request, $urls): bool {
        $request++;
        $this->assertSame($request === 1 ? array_slice($urls, 0, 6) : [$urls[6]], $chunk);
        return $request === 1;
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('error');
    $purger = $this->createPurger($client, logger: $logger);

    $purger->invalidate($invalidations);

    foreach (array_slice($invalidations, 0, 6) as $invalidation) {
      $this->assertSame(InvalidationInterface::SUCCEEDED, $invalidation->getState());
      $this->assertNull($invalidation->getProperty('attempts'));
    }
    $this->assertSame(InvalidationInterface::FAILED, $invalidations[6]->getState());
    $this->assertSame(1, $invalidations[6]->getProperty('attempts'));
  }

  /**
   * Tests that a rewritten URL completes its source invalidation.
   */
  public function testRewrittenUrlSucceeds(): void {
    $source = 'https://www.mass.gov/node/1';
    $rewritten = 'https://www.mass.gov/rewritten';
    $client = $this->createClient();
    $client->expects($this->once())->method('purgeRequest')->with([$rewritten])->willReturn(TRUE);

    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(AkamaiPurgeEvents::PURGE_CREATION, static function (AkamaiPurgeEvents $event) use ($rewritten): void {
      $event->data = [$rewritten];
    });
    $purger = $this->createPurger($client, $dispatcher);

    $invalidation = $this->createUrlInvalidation(1, $source);
    $purger->invalidate([$invalidation]);

    $this->assertSame(InvalidationInterface::SUCCEEDED, $invalidation->getState());
    $this->assertNull($invalidation->getProperty('attempts'));
  }

  /**
   * Tests that path payloads are not hashed and remain relative for Akamai.
   */
  public function testPathPurgeIsNotHashed(): void {
    $path = 'about';
    $url = 'https://www.mass.gov/node/456';
    $client = $this->createClient();
    $client->expects($this->once())
      ->method('purgeRequest')
      ->with([$path, $url])
      ->willReturn(TRUE);

    $dispatcher = new EventDispatcher();
    $logger = $this->createMock(LoggerInterface::class);
    $events = [];
    $dispatcher->addListener(AkamaiPurgeEvents::PURGE_CREATION, static function (AkamaiPurgeEvents $event) use (&$events): void {
      $events[] = $event;
    });
    $dispatcher->addSubscriber(new AkamaiHeaderCreationSubscriber(new CacheTagFormatter(), $logger));
    $purger = $this->createPurger($client, $dispatcher, $logger);

    $path_invalidation = $this->createPathInvalidation(1, $path);
    $url_invalidation = $this->createUrlInvalidation(2, $url);

    $purger->invalidate([$path_invalidation, $url_invalidation]);

    $this->assertCount(2, $events);
    $this->assertSame(['/' . $path], $events[0]->data);
    $this->assertSame([$url], $events[1]->data);
    $this->assertSame(InvalidationInterface::SUCCEEDED, $path_invalidation->getState());
    $this->assertSame(InvalidationInterface::SUCCEEDED, $url_invalidation->getState());
  }

  /**
   * Tests that all URLs added for one invalidation must be purged.
   */
  public function testAddedUrlFailureCountsOnce(): void {
    $source = 'https://www.mass.gov/node/1';
    $extra_urls = [];
    for ($id = 1; $id <= 6; $id++) {
      $extra_urls[] = "https://www.mass.gov/extra/$id";
    }
    $client = $this->createClient();
    $client->expects($this->exactly(2))->method('purgeRequest')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);

    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(AkamaiPurgeEvents::PURGE_CREATION, static function (AkamaiPurgeEvents $event) use ($source, $extra_urls): void {
      $event->data = [$source, ...$extra_urls];
    });
    $purger = $this->createPurger($client, $dispatcher);

    $invalidation = $this->createUrlInvalidation(1, $source);
    $purger->invalidate([$invalidation]);

    $this->assertSame(InvalidationInterface::FAILED, $invalidation->getState());
    $this->assertSame(1, $invalidation->getProperty('attempts'));
  }

  /**
   * Tests that a URL removed before sending still gets a retry state.
   */
  public function testRemovedUrlRecordsFailedAttempt(): void {
    $urls = ['https://www.mass.gov/node/1', 'https://www.mass.gov/node/2'];
    $client = $this->createClient();
    $client->expects($this->once())->method('purgeRequest')->with([$urls[0]])->willReturn(TRUE);

    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(AkamaiPurgeEvents::PURGE_CREATION, static function (AkamaiPurgeEvents $event) use ($urls): void {
      $event->data = $event->data[0] === $urls[0] ? [$urls[0]] : [];
    });
    $purger = $this->createPurger($client, $dispatcher);

    $sent = $this->createUrlInvalidation(1, $urls[0]);
    $removed = $this->createUrlInvalidation(2, $urls[1]);
    $purger->invalidate([$sent, $removed]);

    $this->assertSame(InvalidationInterface::SUCCEEDED, $sent->getState());
    $this->assertSame(InvalidationInterface::FAILED, $removed->getState());
    $this->assertSame(1, $removed->getProperty('attempts'));
  }

  /**
   * Tests that an empty path cannot remain in the processing state.
   */
  public function testEmptyPathRecordsFailedAttempt(): void {
    $client = $this->createClient();
    $client->expects($this->never())->method('purgeRequest');
    $purger = $this->createPurger($client);

    $invalidation = $this->createPathInvalidation(1, '');
    $purger->invalidate([$invalidation]);

    $this->assertSame(InvalidationInterface::FAILED, $invalidation->getState());
    $this->assertSame(1, $invalidation->getProperty('attempts'));
  }

  /**
   * Tests that request exceptions do not stop later chunks.
   */
  public function testRequestExceptionRecordsFailedAttempt(): void {
    $invalidations = [];
    for ($id = 1; $id <= 7; $id++) {
      $invalidations[] = $this->createUrlInvalidation($id, "https://www.mass.gov/node/$id");
    }
    $requests = 0;
    $client = $this->createClient();
    $client->expects($this->exactly(2))
      ->method('purgeRequest')
      ->willReturnCallback(static function () use (&$requests): bool {
        if (++$requests === 1) {
          throw new \RuntimeException('Connection lost');
        }
        return TRUE;
      });
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Akamai URL purge request failed: @message', ['@message' => 'Connection lost']);
    $purger = $this->createPurger($client, logger: $logger);

    $purger->invalidate($invalidations);

    foreach (array_slice($invalidations, 0, 6) as $invalidation) {
      $this->assertSame(InvalidationInterface::FAILED, $invalidation->getState());
      $this->assertSame(1, $invalidation->getProperty('attempts'));
    }
    $this->assertSame(InvalidationInterface::SUCCEEDED, $invalidations[6]->getState());
  }

  /**
   * Creates a client with only the outgoing purge request mocked.
   */
  private function createClient(): AkamaiClientV3&MockObject {
    return $this->getMockBuilder(AkamaiClientV3::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['purgeRequest'])
      ->getMock();
  }

  /**
   * Creates a real purger implementation with test dependencies.
   */
  private function createPurger(AkamaiClientV3 $client, ?EventDispatcher $dispatcher = NULL, ?LoggerInterface $logger = NULL): AkamaiPurger {
    $purger = $this->getMockBuilder(AkamaiPurger::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $this->setPurgerProperty($purger, 'client', $client);
    $this->setPurgerProperty($purger, 'eventDispatcher', $dispatcher ?? new EventDispatcher());
    $purger->setLogger($logger ?? $this->createMock(LoggerInterface::class));
    return $purger;
  }

  /**
   * Creates a path invalidation in the Akamai purger context.
   */
  private function createPathInvalidation(int $id, string $path): PathInvalidation {
    $invalidation = new PathInvalidation([], 'path', [
      'label' => 'Path',
      'expression_required' => TRUE,
      'expression_can_be_empty' => TRUE,
      'expression_must_be_string' => TRUE,
    ], $id, $path);
    $invalidation->setStateContext('akamai');
    return $invalidation;
  }

  /**
   * Creates a URL invalidation in the Akamai purger context.
   */
  private function createUrlInvalidation(int $id, string $url): UrlInvalidation {
    $invalidation = new UrlInvalidation([], 'url', [
      'label' => 'URL',
      'expression_required' => TRUE,
      'expression_can_be_empty' => FALSE,
      'expression_must_be_string' => FALSE,
    ], $id, $url);
    $invalidation->setStateContext('akamai');
    return $invalidation;
  }

  /**
   * Sets an inherited purger property for a test double.
   */
  private function setPurgerProperty(AkamaiPurger $purger, string $property, mixed $value): void {
    $reflection_property = new \ReflectionProperty($purger, $property);
    $reflection_property->setValue($purger, $value);
  }

}
