<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\akamai\Helper\CacheTagFormatter;
use Drupal\mass_caching\AkamaiTagPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the custom Akamai tag purger.
 */
#[CoversClass(AkamaiTagPurger::class)]
#[Group('mass_caching')]
class AkamaiTagPurgerTest extends UnitTestCase {

  /**
   * Tests that repeated failures are logged and removed from the queue.
   */
  #[DataProvider('purgeFailureProvider')]
  public function testRepeatedFailuresAreAbandoned(bool $connection_failure): void {
    $container = new ContainerBuilder();
    $container->set('akamai.helper.cachetagformatter', new CacheTagFormatter());
    \Drupal::setContainer($container);

    $client = $this->getMockBuilder('Drupal\akamai\Plugin\Client\AkamaiClientV3')
      ->disableOriginalConstructor()
      ->onlyMethods(['setType', 'purgeTags'])
      ->getMock();
    $client->expects($this->exactly(AkamaiTagPurger::MAX_ATTEMPTS))
      ->method('purgeTags')
      ->with(['node_123'])
      ->willReturnCallback(static function () use ($connection_failure): bool {
        if ($connection_failure) {
          throw new ConnectException('Connection timed out', new Request('POST', 'https://example.invalid'));
        }
        return FALSE;
      });

    $errors = [];
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->exactly($connection_failure ? AkamaiTagPurger::MAX_ATTEMPTS + 1 : 1))
      ->method('error')
      ->willReturnCallback(static function (string $message, array $context) use (&$errors): void {
        $errors[] = [$message, $context];
      });

    $purger = $this->getMockBuilder(AkamaiTagPurger::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $this->setPurgerProperty($purger, 'client', $client);
    $this->setPurgerProperty($purger, 'eventDispatcher', new EventDispatcher());
    $this->setPurgerProperty($purger, 'logger', $logger);

    $invalidation = new TagInvalidation([], 'tag', [
      'label' => 'Tag',
      'expression_required' => TRUE,
      'expression_can_be_empty' => FALSE,
      'expression_must_be_string' => TRUE,
    ], 1, 'node:123');
    $invalidation->setStateContext('akamai_tag');

    for ($attempt = 1; $attempt <= AkamaiTagPurger::MAX_ATTEMPTS; $attempt++) {
      $purger->invalidate([$invalidation]);

      $this->assertSame($attempt, $invalidation->getProperty('attempts'));
      $expected_state = $attempt === AkamaiTagPurger::MAX_ATTEMPTS
        ? InvalidationInterface::SUCCEEDED
        : InvalidationInterface::FAILED;
      $this->assertSame($expected_state, $invalidation->getState());
    }

    $expected_errors = $connection_failure ? array_fill(0, AkamaiTagPurger::MAX_ATTEMPTS, [
      'Akamai cache tag purge request failed: @message',
      ['@message' => 'Connection timed out'],
    ]) : [];
    $expected_errors[] = [
      'Akamai @type purge failed after @attempts attempts for %item. Removing the invalidation from the queue.',
      [
        '@type' => 'cache tag',
        '@attempts' => AkamaiTagPurger::MAX_ATTEMPTS,
        '%item' => 'node:123',
      ],
    ];
    $this->assertSame($expected_errors, $errors);
  }

  /**
   * Provides API failures and transport exceptions.
   */
  public static function purgeFailureProvider(): array {
    return [
      'API failure' => [FALSE],
      'connection timeout' => [TRUE],
    ];
  }

  /**
   * Sets an inherited purger property for a test double.
   */
  private function setPurgerProperty(AkamaiTagPurger $purger, string $property, mixed $value): void {
    $reflection_property = new \ReflectionProperty($purger, $property);
    $reflection_property->setValue($purger, $value);
  }

}
