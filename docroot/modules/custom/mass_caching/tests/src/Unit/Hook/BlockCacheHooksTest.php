<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\mass_caching\Hook\BlockCacheHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests block cache hook implementations.
 */
#[CoversClass(BlockCacheHooks::class)]
#[Group('mass_caching')]
class BlockCacheHooksTest extends UnitTestCase {

  /**
   * Tests that local task tabs are not cached.
   */
  public function testLocalTasksAreNotCached(): void {
    $build = [
      '#id' => 'mass_theme_tabs',
      '#cache' => [
        'max-age' => 3600,
      ],
    ];

    (new BlockCacheHooks())->blockViewAlter($build, $this->createMock(BlockPluginInterface::class));

    $this->assertSame(0, $build['#cache']['max-age']);
  }

  /**
   * Tests that unrelated blocks are left unchanged.
   */
  public function testOtherBlocksAreLeftCacheable(): void {
    $build = [
      '#id' => 'other_block',
      '#cache' => [
        'max-age' => 3600,
      ],
    ];

    (new BlockCacheHooks())->blockViewAlter($build, $this->createMock(BlockPluginInterface::class));

    $this->assertSame(3600, $build['#cache']['max-age']);
  }

}
