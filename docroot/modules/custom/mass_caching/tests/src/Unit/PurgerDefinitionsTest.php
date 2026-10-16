<?php

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the environment-specific purger definitions.
 *
 * @group mass_caching
 */
class PurgerDefinitionsTest extends UnitTestCase {

  /**
   * Tests that only explicitly enabled purgers remain discoverable.
   */
  public function testDisabledPurgersAreRemoved(): void {
    $original_environment = getenv('AH_SITE_ENVIRONMENT');
    $original_purgers = getenv('MASS_PURGERS');
    putenv('AH_SITE_ENVIRONMENT');
    putenv('MASS_PURGERS');

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(FALSE);
    $container = new ContainerBuilder();
    $container->set('state', $state);
    \Drupal::setContainer($container);

    if (!function_exists('mass_caching_purge_purgers_alter')) {
      require_once DRUPAL_ROOT . '/modules/custom/mass_caching/mass_caching.module';
    }

    try {
      $definitions = $this->definitions();
      mass_caching_purge_purgers_alter($definitions);
      $this->assertSame(['other' => ['types' => ['url']]], $definitions);

      putenv('MASS_PURGERS=akamai');
      $definitions = $this->definitions();
      mass_caching_purge_purgers_alter($definitions);
      $this->assertSame(['url', 'path'], $definitions['akamai']['types']);
      $this->assertArrayNotHasKey('akamai_tag', $definitions);
      $this->assertArrayNotHasKey('acquia_purge', $definitions);
    }
    finally {
      putenv($original_environment === FALSE
        ? 'AH_SITE_ENVIRONMENT'
        : 'AH_SITE_ENVIRONMENT=' . $original_environment);
      putenv($original_purgers === FALSE
        ? 'MASS_PURGERS'
        : 'MASS_PURGERS=' . $original_purgers);
    }
  }

  /**
   * Returns representative purger definitions.
   */
  private function definitions(): array {
    return [
      'akamai' => ['types' => ['url', 'path']],
      'akamai_tag' => ['types' => ['tag']],
      'acquia_purge' => ['types' => ['url', 'tag', 'everything']],
      'other' => ['types' => ['url']],
    ];
  }

}
