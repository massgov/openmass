<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_bulk_file_replace\Traits;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Shared helpers for bulk file replace ExistingSite tests.
 */
trait BulkFileReplaceTestTrait {

  /**
   * Registers a file entity for test cleanup.
   */
  abstract protected function registerFileForCleanup(File $file): void;

  /**
   * URI scheme path for temporary bulk-replace uploads.
   */
  protected function bulkReplaceTempDirectory(): string {
    return 'temporary://mass_bulk_file_replace';
  }

  /**
   * Ensures the temporary bulk-replace upload directory exists.
   */
  protected function ensureBulkReplaceTempDirectory(): void {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $directory = $this->bulkReplaceTempDirectory();
    $flags = FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS;
    $fs->prepareDirectory($directory, $flags);
  }

  /**
   * Returns the private tempstore used by bulk file replace forms.
   */
  protected function bulkReplaceTempstore() {
    return \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
  }

  /**
   * Builds the mismatch tempstore key for a user.
   */
  protected function mismatchTempstoreKey(int $uid): string {
    return 'mismatch_files_' . $uid;
  }

  /**
   * Clears mismatch tempstore for a user.
   */
  protected function clearMismatchTempstore(int $uid): void {
    $this->bulkReplaceTempstore()->delete($this->mismatchTempstoreKey($uid));
  }

  /**
   * Seeds mismatch tempstore for a user.
   *
   * @param int[] $fids
   *   File entity ids.
   */
  protected function seedMismatchTempstore(int $uid, array $fids): void {
    $this->bulkReplaceTempstore()->set($this->mismatchTempstoreKey($uid), $fids);
  }

  /**
   * Asserts the safe-match path never wrote mismatch tempstore data.
   */
  protected function assertMismatchTempstoreUnset(int $uid): void {
    $this->assertNull($this->bulkReplaceTempstore()->get($this->mismatchTempstoreKey($uid)));
  }

  /**
   * Asserts mismatch tempstore contains the expected number of file ids.
   *
   * @return int[]
   *   The mismatch file ids from tempstore.
   */
  protected function assertMismatchTempstoreCount(int $uid, int $expected): array {
    $mismatch = $this->bulkReplaceTempstore()->get($this->mismatchTempstoreKey($uid));
    $this->assertIsArray($mismatch);
    $this->assertCount($expected, $mismatch);
    return $mismatch;
  }

  /**
   * Creates a temporary mismatch upload file entity.
   *
   * @param bool $include_media_token
   *   When FALSE, filename is basename only (no media ID token).
   */
  protected function createMismatchUploadFile(int $mid, string $basename, string $contents, string $uri_suffix, bool $include_media_token = TRUE): File {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $this->ensureBulkReplaceTempDirectory();
    $filename = $include_media_token
      ? $basename . '_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf'
      : $basename . '.pdf';
    $uri = $this->bulkReplaceTempDirectory() . '/' . $uri_suffix . '-' . $this->randomMachineName() . '.pdf';
    $fs->saveData($contents, $uri, FileExists::Replace);
    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 0,
    ]);
    $file->save();
    $this->registerFileForCleanup($file);
    return $file;
  }

}
