<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\mass_caching\ManualPurger;

/**
 * Hook implementations for file purge invalidations.
 */
class FilePurgeHooks {

  /**
   * Constructs a FilePurgeHooks object.
   */
  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected ManualPurger $manualPurger,
  ) {}

  /**
   * Purge the file's path when a file is created, updated, deleted, or moved.
   */
  #[Hook('file_insert')]
  #[Hook('file_update')]
  #[Hook('file_delete')]
  #[Hook('file_move')]
  public function purgeFile(FileInterface $file, ?FileInterface $source = NULL): void {
    if ($this->uriIsPrivate($file)) {
      return;
    }
    // Must purge the file on all domains/schemes, so we use a path purge here,
    // which is converted to a URL.
    $absolute = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    $relative = $this->fileUrlGenerator->transformRelative($absolute);
    $this->manualPurger->purgePath($relative);
  }

  /**
   * Determine whether a file uri is private.
   */
  protected function uriIsPrivate(FileInterface $file): bool {
    $destination_scheme = $this->streamWrapperManager::getScheme($file->getFileUri());
    return $destination_scheme === 'private';
  }

}
