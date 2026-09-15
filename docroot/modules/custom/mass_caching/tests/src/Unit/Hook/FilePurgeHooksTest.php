<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_caching\Unit\Hook;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\mass_caching\Hook\FilePurgeHooks;
use Drupal\mass_caching\ManualPurger;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerInterface;

/**
 * Tests file purge hook implementations.
 */
#[CoversClass(FilePurgeHooks::class)]
#[Group('mass_caching')]
class FilePurgeHooksTest extends UnitTestCase {

  /**
   * Tests that public files purge their root-relative file path.
   */
  public function testPublicFilePurgesRelativePath(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('public://documents/report.pdf');

    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $file_url_generator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with('public://documents/report.pdf')
      ->willReturn('https://www.mass.gov/sites/default/files/documents/report.pdf');
    $file_url_generator->expects($this->once())
      ->method('transformRelative')
      ->with('https://www.mass.gov/sites/default/files/documents/report.pdf')
      ->willReturn('/sites/default/files/documents/report.pdf');

    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->once())
      ->method('purgePath')
      ->with('/sites/default/files/documents/report.pdf');

    $hooks = new FilePurgeHooks(
      $file_url_generator,
      new StreamWrapperManager($this->createMock(ContainerInterface::class)),
      $manual_purger,
    );

    $hooks->purgeFile($file);
  }

  /**
   * Tests that private files are not purged.
   */
  public function testPrivateFileDoesNotPurge(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('private://documents/report.pdf');

    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $file_url_generator->expects($this->never())
      ->method('generateAbsoluteString');

    $manual_purger = $this->createMock(ManualPurger::class);
    $manual_purger->expects($this->never())
      ->method('purgePath');

    $hooks = new FilePurgeHooks(
      $file_url_generator,
      new StreamWrapperManager($this->createMock(ContainerInterface::class)),
      $manual_purger,
    );

    $hooks->purgeFile($file);
  }

}
