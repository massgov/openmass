<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityListBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the Able Player installer and recovery of its incomplete displays.
 */
#[Group('mass_media')]
class AblePlayerInstallTest extends UnitTestCase {

  /**
   * Creates the small container used by the module installation hooks.
   */
  private function installContainer(): array {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $container->set('entity_type.manager', $manager);
    $container->set('config.factory', $factory);
    \Drupal::setContainer($container);
    require_once DRUPAL_ROOT . '/modules/contrib/ableplayer/ableplayer.module';
    require_once DRUPAL_ROOT . '/modules/custom/mass_media/mass_media.install';
    return [$manager, $factory];
  }

  /**
   * Configuration imports own their fields and displays during installation.
   */
  public function testConfigImportDoesNotWriteInstallerConfiguration(): void {
    [$manager, $factory] = $this->installContainer();
    $manager->expects($this->never())->method('getListBuilder');
    $factory->expects($this->never())->method('getEditable');
    ableplayer_modules_installed(['ableplayer'], TRUE);
  }

  /**
   * Ordinary installation does not invent displays for a missing Video bundle.
   */
  public function testMissingVideoDisplaysAreNotCreated(): void {
    [$manager, $factory] = $this->installContainer();
    $bundles = $this->createMock(EntityListBuilderInterface::class);
    $bundles->method('load')->willReturn([]);
    $manager->method('getListBuilder')->with('media_type')->willReturn($bundles);
    $missing = $this->createMock(Config::class);
    $missing->method('isNew')->willReturn(TRUE);
    $missing->expects($this->never())->method('set');
    $missing->expects($this->never())->method('save');
    $factory->expects($this->exactly(2))->method('getEditable')->willReturn($missing);
    ableplayer_modules_installed(['ableplayer']);
  }

  /**
   * Recovery removes only the malformed records that block entity deletion.
   */
  #[DataProvider('displayStates')]
  public function testRepairPreservesValidDisplays(bool $is_new, array $metadata, bool $delete): void {
    [, $factory] = $this->installContainer();
    $config = $this->createMock(Config::class);
    $config->method('isNew')->willReturn($is_new);
    $config->method('get')->willReturnCallback(fn($key) => $metadata[$key] ?? NULL);
    $config->expects($delete ? $this->exactly(2) : $this->never())->method('delete');
    $factory->expects($this->exactly(2))->method('getEditable')->willReturn($config);
    mass_media_update_10001();
  }

  /**
   * Provides both corrupt installer shapes and legitimate display metadata.
   */
  public static function displayStates(): array {
    return [
      'absent' => [TRUE, [], FALSE],
      'installer fragment' => [FALSE, ['content' => ['field_media_video_file' => ['type' => 'ableplayer_video']]], TRUE],
      'missing bundle' => [FALSE, ['targetEntityType' => 'media'], TRUE],
      'missing target type' => [FALSE, ['bundle' => 'video'], TRUE],
      'valid display' => [FALSE, ['targetEntityType' => 'media', 'bundle' => 'video'], FALSE],
    ];
  }

}
