<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_bulk_file_replace\ExistingSite;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\mass_bulk_file_replace\FilenameMediaMatchTrait;
use Drupal\mass_bulk_file_replace\Form\ReplaceUploadForm;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests bulk file replace filename matching and batch processing.
 *
 * @group existing-site
 */
class BulkFileReplaceTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Exposes FilenameMediaMatchTrait helpers for assertions.
   */
  private function traitHelper(): object {
    return new class() {
      use FilenameMediaMatchTrait;

      /**
       * Public wrapper for extractMediaId().
       */
      public static function extractMediaIdPublic(string $filename): ?int {
        return static::extractMediaId($filename);
      }

      /**
       * Public wrapper for getDisplayFilename().
       */
      public static function getDisplayFilenamePublic(string $filename): string {
        return static::getDisplayFilename($filename);
      }

      /**
       * Public wrapper for isSafeFilenameMatch().
       */
      public static function isSafeFilenameMatchPublic(string $uploaded, string $existing): bool {
        return static::isSafeFilenameMatch($uploaded, $existing);
      }

    };
  }

  /**
   * Creates a test user and sets current account.
   */
  private function createTestUser(): UserInterface {
    $user = $this->createUser();
    $user->activate();
    $user->save();
    $this->cleanupEntities[] = $user;
    \Drupal::currentUser()->setAccount($user);
    return $user;
  }

  /**
   * Clears mismatch tempstore keys for a user.
   */
  private function clearMismatchTempstore(int $uid): void {
    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $store->delete('mismatch_files_' . $uid);
  }

  /**
   * Saves binary data to a URI under temporary bulk replace dir.
   */
  private function writeTempUpload(string $relative_name, string $contents): string {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $dir = 'temporary://mass_bulk_file_replace';
    $flags = FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS;
    $fs->prepareDirectory($dir, $flags);
    $uri = $dir . '/' . $relative_name;
    $fs->saveData($contents, $uri, FileExists::Replace);
    $real = $fs->realpath($uri);
    $this->assertNotFalse($real);
    return $real;
  }

  /**
   * Creates a document media item with a managed file.
   */
  private function createDocumentMedia(string $filename, string $contents = 'original'): MediaInterface {
    $destination = 'public://mass-bulk-file-replace-test-' . $this->randomMachineName() . '-' . $filename;
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $fs->saveData($contents, $destination, FileExists::Replace);
    $file = File::create([
      'uri' => $destination,
      'filename' => $filename,
    ]);
    $file->setPermanent();
    $file->save();
    $this->cleanupEntities[] = $file;

    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Bulk replace test ' . $this->randomMachineName(),
      'field_upload_file' => ['target_id' => $file->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->cleanupEntities[] = $media;

    return $media;
  }

  /**
   * Tests FilenameMediaMatchTrait helpers.
   */
  public function testTraitHelpers(): void {
    $h = $this->traitHelper();
    $this->assertSame(42, $h::extractMediaIdPublic('foo_DO_NOT_CHANGE_THIS_MEDIA_ID_42.pdf'));
    $this->assertSame(42, $h::extractMediaIdPublic('foo_2_DO_NOT_CHANGE_THIS_MEDIA_ID_42.pdf'));
    $this->assertNull($h::extractMediaIdPublic('foo.pdf'));
    $this->assertSame('foo.pdf', $h::getDisplayFilenamePublic('foo_DO_NOT_CHANGE_THIS_MEDIA_ID_42.pdf'));
    $this->assertTrue($h::isSafeFilenameMatchPublic('foo.pdf', 'foo.pdf'));
    $this->assertTrue($h::isSafeFilenameMatchPublic('housing-proposal2-ally.pdf', 'housing-proposal2.pdf'));
    $this->assertTrue($h::isSafeFilenameMatchPublic('housing-proposal2_revised.pdf', 'housing-proposal2.pdf'));
    $this->assertFalse($h::isSafeFilenameMatchPublic('foo.pdf', 'bar.pdf'));
    $this->assertTrue($h::isSafeFilenameMatchPublic('foo_DO_NOT_CHANGE_THIS_MEDIA_ID_42.pdf', 'foo_0.pdf'));
  }

  /**
   * Safe exact filename match auto-replaces without tempstore mismatch.
   */
  public function testProcessFileBatchExactMatchAutoReplaces(): void {
    $user = $this->createTestUser();
    $uid = (int) $user->id();
    $this->clearMismatchTempstore($uid);

    $media = $this->createDocumentMedia('housing-proposal.pdf', 'old-content');
    $mid = (int) $media->id();
    $upload_name = 'housing-proposal_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf';
    $path = $this->writeTempUpload($this->randomMachineName() . '-upload.pdf', 'new-content');

    $context = [];
    ReplaceUploadForm::processFileBatch([
      'filename' => $upload_name,
      'path' => $path,
    ], $uid, 'tester', 'verified_accessible', $context);

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $mismatch = $store->get('mismatch_files_' . $uid) ?? [];
    $this->assertSame([], $mismatch);

    $reloaded = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $this->assertNotNull($reloaded);
    $this->assertInstanceOf(MediaInterface::class, $reloaded);
    $file = $reloaded->get('field_upload_file')->entity;
    $this->assertNotNull($file);
    $this->assertSame('housing-proposal.pdf', $file->getFilename());
    $this->assertSame('verified_accessible', (string) $reloaded->get('field_accessibility_self_rpt')->value);
  }

  /**
   * Uploaded base starting with existing base is a safe match.
   */
  public function testProcessFileBatchSafePrefixAutoReplaces(): void {
    $user = $this->createTestUser();
    $uid = (int) $user->id();
    $this->clearMismatchTempstore($uid);

    $media = $this->createDocumentMedia('housing-proposal2.pdf');
    $mid = (int) $media->id();
    $upload_name = 'housing-proposal2-ally_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf';
    $path = $this->writeTempUpload($this->randomMachineName() . '-upload2.pdf', 'revised');

    $context = [];
    ReplaceUploadForm::processFileBatch([
      'filename' => $upload_name,
      'path' => $path,
    ], $uid, 'tester', '', $context);

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $mismatch = $store->get('mismatch_files_' . $uid) ?? [];
    $this->assertSame([], $mismatch);

    $reloaded = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $this->assertNotNull($reloaded);
    $this->assertInstanceOf(MediaInterface::class, $reloaded);
    $file = $reloaded->get('field_upload_file')->entity;
    $this->assertSame('housing-proposal2-ally.pdf', $file->getFilename());
  }

  /**
   * Different basenames go to mismatch tempstore.
   */
  public function testProcessFileBatchMismatchGoesToTempstore(): void {
    $user = $this->createTestUser();
    $uid = (int) $user->id();
    $this->clearMismatchTempstore($uid);

    $media = $this->createDocumentMedia('report.pdf');
    $mid = (int) $media->id();
    $upload_name = 'brand-new_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf';
    $path = $this->writeTempUpload($this->randomMachineName() . '-mismatch.pdf', 'x');

    $context = [];
    ReplaceUploadForm::processFileBatch([
      'filename' => $upload_name,
      'path' => $path,
    ], $uid, 'tester', '', $context);

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $mismatch = $store->get('mismatch_files_' . $uid) ?? [];
    $this->assertCount(1, $mismatch);
    $this->assertArrayHasKey(0, $mismatch);
    $fid = $mismatch[0];
    $uploaded = File::load($fid);
    $this->assertInstanceOf(File::class, $uploaded);
    $this->assertSame($upload_name, $uploaded->getFilename());
    $this->cleanupEntities[] = $uploaded;
  }

  /**
   * Filename without media id token goes to mismatch tempstore.
   */
  public function testProcessFileBatchMissingTokenGoesToTempstore(): void {
    $user = $this->createTestUser();
    $uid = (int) $user->id();
    $this->clearMismatchTempstore($uid);

    $path = $this->writeTempUpload($this->randomMachineName() . '-orphan.pdf', 'y');

    $context = [];
    ReplaceUploadForm::processFileBatch([
      'filename' => 'orphan.pdf',
      'path' => $path,
    ], $uid, 'tester', '', $context);

    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $mismatch = $store->get('mismatch_files_' . $uid) ?? [];
    $this->assertCount(1, $mismatch);
    $this->assertArrayHasKey(0, $mismatch);
    $fid = $mismatch[0];
    $uploaded = File::load($fid);
    $this->assertInstanceOf(File::class, $uploaded);
    $this->cleanupEntities[] = $uploaded;
  }

  /**
   * Validates duplicate media IDs in one upload are rejected.
   */
  public function testValidateRejectsDuplicateMediaIds(): void {
    $form = ReplaceUploadForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_state->setValues([
      'upload' => [
        'uploaded_files' => [
          [
            'filename' => 'a_DO_NOT_CHANGE_THIS_MEDIA_ID_555.pdf',
            'path' => '/tmp/a',
          ],
          [
            'filename' => 'b_DO_NOT_CHANGE_THIS_MEDIA_ID_555.pdf',
            'path' => '/tmp/b',
          ],
        ],
      ],
    ]);

    $form_array = [];
    $form->validateForm($form_array, $form_state);
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Duplicate uploads detected for Media ID(s)', implode(' ', array_map('strval', $errors)));
  }

}
