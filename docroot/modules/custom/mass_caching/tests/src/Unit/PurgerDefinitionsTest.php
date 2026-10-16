<?php

namespace Drupal\Tests\mass_caching\Unit;

use Drupal\Core\State\StateInterface;
use Drupal\mass_caching\PurgerSelection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the environment-specific purger definitions.
 *
 * @group mass_caching
 */
class PurgerDefinitionsTest extends UnitTestCase {

  /**
   * Tests the purgers enabled for each environment and local override.
   */
  #[DataProvider('environmentProvider')]
  public function testEnabledPurgersForEnvironment(string|false $environment, string|false $override, array $expected): void {
    $this->assertSame($expected, PurgerSelection::enabledPurgers($environment, $override));
  }

  /**
   * Provides environment and override combinations.
   */
  public static function environmentProvider(): array {
    return [
      'production' => ['prod', FALSE, ['acquia_purge', 'akamai', 'akamai_tag']],
      'test' => ['test', FALSE, ['acquia_purge', 'akamai', 'akamai_tag']],
      'development' => ['dev', FALSE, ['acquia_purge']],
      'ODE' => ['ode', FALSE, ['acquia_purge']],
      'local' => [FALSE, FALSE, []],
      'local Akamai override' => [FALSE, 'akamai, akamai_tag', ['akamai', 'akamai_tag']],
      'local Acquia override' => [FALSE, 'acquia_purge', ['acquia_purge']],
      'development tag override' => ['dev', 'akamai_tag', ['acquia_purge', 'akamai_tag']],
      'unknown override' => [FALSE, 'definitions', []],
      'duplicate override' => [FALSE, 'akamai,akamai', ['akamai']],
    ];
  }

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

      putenv('MASS_PURGERS');
      putenv('AH_SITE_ENVIRONMENT=prod');
      $definitions = $this->definitions();
      mass_caching_purge_purgers_alter($definitions);
      $this->assertSame($this->definitions(), $definitions);
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
