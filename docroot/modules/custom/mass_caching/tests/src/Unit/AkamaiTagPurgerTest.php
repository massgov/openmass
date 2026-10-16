<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\akamai\Helper\CacheTagFormatter;
use Drupal\mass_caching\AkamaiPurger;
use Drupal\mass_caching\AkamaiTagPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation;
use Drupal\Tests\UnitTestCase;
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
   * Tests that both Akamai purgers use their custom classes when enabled.
   */
  #[DataProvider('akamaiEnvironmentProvider')]
  public function testPurgerDefinitionsAreSwapped(string $environment): void {
    if (!function_exists('mass_caching_purge_purgers_alter')) {
      require_once DRUPAL_ROOT . '/modules/custom/mass_caching/mass_caching.module';
    }

    $definitions = [
      'akamai' => ['class' => 'OriginalUrlPurger'],
      'akamai_tag' => ['class' => 'OriginalTagPurger'],
    ];
    $original_environment = getenv('AH_SITE_ENVIRONMENT');
    $original_purgers = getenv('MASS_PURGERS');
    putenv('AH_SITE_ENVIRONMENT=' . $environment);
    putenv('MASS_PURGERS');
    try {
      mass_caching_purge_purgers_alter($definitions);
    }
    finally {
      putenv($original_environment === FALSE
        ? 'AH_SITE_ENVIRONMENT'
        : 'AH_SITE_ENVIRONMENT=' . $original_environment);
      putenv($original_purgers === FALSE
        ? 'MASS_PURGERS'
        : 'MASS_PURGERS=' . $original_purgers);
    }

    $this->assertSame(AkamaiPurger::class, $definitions['akamai']['class']);
    $this->assertSame(AkamaiTagPurger::class, $definitions['akamai_tag']['class']);
  }

  /**
   * Provides environments where Akamai purging is enabled by default.
   */
  public static function akamaiEnvironmentProvider(): array {
    return [
      'production' => ['prod'],
      'test' => ['test'],
    ];
  }

  /**
   * Tests that repeated failures are logged and removed from the queue.
   */
  public function testRepeatedFailuresAreAbandoned(): void {
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
      ->willReturn(FALSE);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Akamai @type purge failed after @attempts attempts for %item. Removing the invalidation from the queue.',
        [
          '@type' => 'cache tag',
          '@attempts' => AkamaiTagPurger::MAX_ATTEMPTS,
          '%item' => 'node:123',
        ]
      );

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
  }

  /**
   * Sets an inherited purger property for a test double.
   */
  private function setPurgerProperty(AkamaiTagPurger $purger, string $property, mixed $value): void {
    $reflection_property = new \ReflectionProperty($purger, $property);
    $reflection_property->setValue($purger, $value);
  }

}
