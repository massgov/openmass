<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\mass_media\StageFileProxyHelper;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\mass_media\StageFileProxyHelper
 *
 * @group mass_media
 */
class StageFileProxyHelperTest extends UnitTestCase {

  /**
   * @covers ::ensureLocalFileUri
   */
  public function testEnsureLocalFileUriReturnsTrueWhenFileAlreadyExists(): void {
    $existing = $this->createTempFile('existing.pdf');

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->with('public://documents/test.pdf')->willReturn($existing);

    $helper = new StageFileProxyHelper(
      $file_system,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $this->assertTrue($helper->ensureLocalFileUri('public://documents/test.pdf'));
  }

  /**
   * @covers ::ensureLocalFileUri
   */
  public function testEnsureLocalFileUriFetchesMissingPublicFileFromOrigin(): void {
    $fetched = sys_get_temp_dir() . '/' . uniqid('mass_media_', TRUE) . '_test.pdf';
    $this->assertFileDoesNotExist($fetched);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturnCallback(function (string $uri) use ($fetched) {
      if ($uri === 'public://documents/test.pdf') {
        return file_exists($fetched) ? $fetched : FALSE;
      }
      return FALSE;
    });

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['origin', 'https://www.mass.gov'],
      ['origin_dir', 'files'],
      ['verify', FALSE],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('stage_file_proxy.settings')->willReturn($config);

    $download_manager = $this->createMock(DownloadManagerInterface::class);
    $download_manager->expects($this->once())
      ->method('fetch')
      ->with('https://www.mass.gov', 'files', 'documents/test.pdf', ['verify' => FALSE])
      ->willReturnCallback(function () use ($fetched) {
        touch($fetched);
        return TRUE;
      });

    $helper = new StageFileProxyHelper(
      $file_system,
      $config_factory,
      $this->createMock(LoggerInterface::class),
      $download_manager,
    );

    $this->assertTrue($helper->ensureLocalFileUri('public://documents/test.pdf'));
  }

  /**
   * @covers ::ensureLocalFile
   */
  public function testEnsureLocalFileCopiesFetchedPublicFileIntoPrivateScheme(): void {
    $public = $this->createTempFile('public-test.pdf');
    $private = $this->createTempFile('private-test.pdf');
    unlink($private);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->method('realpath')->willReturnCallback(function (string $uri) use ($public, $private) {
      return match ($uri) {
        'private://documents/test.pdf' => file_exists($private) ? $private : FALSE,
        'public://documents/test.pdf' => file_exists($public) ? $public : FALSE,
        default => FALSE,
      };
    });
    $file_system->method('dirname')->with('private://documents/test.pdf')->willReturn('private://documents');
    $file_system->expects($this->once())
      ->method('prepareDirectory')
      ->with('private://documents', FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)
      ->willReturn(TRUE);
    $file_system->expects($this->once())
      ->method('copy')
      ->with('public://documents/test.pdf', 'private://documents/test.pdf', $this->anything())
      ->willReturnCallback(function () use ($public, $private) {
        copy($public, $private);
        return 'private://documents/test.pdf';
      });

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['origin', 'https://www.mass.gov'],
      ['origin_dir', 'files'],
      ['verify', FALSE],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('stage_file_proxy.settings')->willReturn($config);

    $download_manager = $this->createMock(DownloadManagerInterface::class);
    $download_manager->expects($this->once())->method('fetch')->willReturnCallback(function () use ($public) {
      touch($public);
      return TRUE;
    });

    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('private://documents/test.pdf');

    $helper = new StageFileProxyHelper(
      $file_system,
      $config_factory,
      $this->createMock(LoggerInterface::class),
      $download_manager,
    );

    $this->assertTrue($helper->ensureLocalFile($file));
  }

  /**
   * Creates a temporary file path for filesystem assertions.
   */
  private function createTempFile(string $name): string {
    $path = sys_get_temp_dir() . '/' . uniqid('mass_media_', TRUE) . '_' . $name;
    touch($path);
    $this->assertFileExists($path);
    return $path;
  }

}
