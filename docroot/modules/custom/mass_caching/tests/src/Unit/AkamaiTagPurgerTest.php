<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\akamai\Helper\CacheTagFormatter;
use Drupal\Core\State\StateInterface;
use Drupal\mass_caching\AkamaiPurger;
use Drupal\mass_caching\AkamaiTagPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
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
   * Tests that the custom purgers are swapped into both Akamai definitions.
   */
  public function testPurgerDefinitionsAreSwapped(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('mass_caching.purger', FALSE)
      ->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('state', $state);
    \Drupal::setContainer($container);

    if (!function_exists('mass_caching_purge_purgers_alter')) {
      require_once DRUPAL_ROOT . '/modules/custom/mass_caching/mass_caching.module';
    }

    $definitions = [
      'akamai' => ['class' => 'OriginalUrlPurger'],
      'akamai_tag' => ['class' => 'OriginalTagPurger'],
    ];
    mass_caching_purge_purgers_alter($definitions);

    $this->assertSame(AkamaiPurger::class, $definitions['akamai']['class']);
    $this->assertSame(AkamaiTagPurger::class, $definitions['akamai_tag']['class']);
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
        'Akamai tag purge failed after @attempts attempts for cache tag %tag. Removing the invalidation from the queue.',
        [
          '@attempts' => AkamaiTagPurger::MAX_ATTEMPTS,
          '%tag' => 'node:123',
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

      $this->assertSame($attempt, $invalidation->getProperty(AkamaiTagPurger::ATTEMPTS_PROPERTY));
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
