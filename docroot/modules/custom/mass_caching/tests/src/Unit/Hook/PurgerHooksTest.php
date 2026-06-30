<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit\Hook;

use Drupal\Core\State\StateInterface;
use Drupal\mass_caching\AkamaiPurger;
use Drupal\mass_caching\Hook\PurgerHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests purger plugin definition hook implementations.
 */
#[CoversClass(PurgerHooks::class)]
#[Group('mass_caching')]
class PurgerHooksTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('AH_SITE_ENVIRONMENT');
    putenv('MASS_PURGERS');
    parent::tearDown();
  }

  /**
   * Tests that state enables the Mass Akamai purger replacement.
   */
  public function testStateSwapsAkamaiPurgerClass(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('mass_caching.purger', FALSE)
      ->willReturn(TRUE);

    $definitions = [
      'akamai' => [
        'class' => 'Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger',
        'types' => ['url'],
      ],
    ];

    (new PurgerHooks($state))->purgePurgersAlter($definitions);

    $this->assertSame(AkamaiPurger::class, $definitions['akamai']['class']);
  }

  /**
   * Tests that the Akamai purger class is unchanged when state is disabled.
   */
  public function testDisabledStateLeavesAkamaiPurgerClassUnchanged(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('mass_caching.purger', FALSE)
      ->willReturn(FALSE);

    $definitions = [
      'akamai' => [
        'class' => 'Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger',
        'types' => ['url'],
      ],
    ];

    (new PurgerHooks($state))->purgePurgersAlter($definitions);

    $this->assertSame('Drupal\akamai\Plugin\Purge\Purger\AkamaiPurger', $definitions['akamai']['class']);
  }

}
