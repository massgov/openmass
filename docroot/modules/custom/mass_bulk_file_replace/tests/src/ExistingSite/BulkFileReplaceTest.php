<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_bulk_file_replace\ExistingSite;

use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\mass_bulk_file_replace\FilenameMediaMatchTrait;
use Drupal\mass_bulk_file_replace\Form\ReplaceMismatchForm;
use Drupal\mass_bulk_file_replace\Form\ReplaceUploadForm;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\media\MediaInterface;
use Drupal\Tests\mass_bulk_file_replace\Traits\BulkFileReplaceTestTrait;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests bulk file replace filename matching and batch processing.
 *
 * @group existing-site
 */
class BulkFileReplaceTest extends MassExistingSiteBase {

  use BulkFileReplaceTestTrait;
  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function registerFileForCleanup(File $file): void {
    $this->cleanupEntities[] = $file;
  }

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
   * Saves binary data to a URI under temporary bulk replace dir.
   */
  private function writeTempUpload(string $relative_name, string $contents): string {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $this->ensureBulkReplaceTempDirectory();
    $uri = $this->bulkReplaceTempDirectory() . '/' . $relative_name;
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
   * Resolves the expected public URI directory for a document media file field.
   */
  private function expectedMediaFileDirectory(MediaInterface $media): string {
    $field_definition = $media->getFieldDefinition('field_upload_file');
    $settings = $field_definition->getSettings();
    $file_directory = $settings['file_directory'] ?? '';
    if ($file_directory === '') {
      return 'public://';
    }
    $file_directory = \Drupal::token()->replace($file_directory, ['media' => $media]);
    return 'public://' . $file_directory;
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

    $this->assertMismatchTempstoreUnset($uid);

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

    $this->assertMismatchTempstoreUnset($uid);

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

    $mismatch = $this->assertMismatchTempstoreCount($uid, 1);
    $this->assertArrayHasKey(0, $mismatch);
    $uploaded = File::load($mismatch[0]);
    $this->assertInstanceOf(File::class, $uploaded);
    $this->assertSame($upload_name, $uploaded->getFilename());
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

    $mismatch = $this->assertMismatchTempstoreCount($uid, 1);
    $this->assertArrayHasKey(0, $mismatch);
    $uploaded = File::load($mismatch[0]);
    $this->assertInstanceOf(File::class, $uploaded);
  }

  /**
   * ReplaceMismatchForm::processBatch replaces file, strips token, clears tempstore.
   */
  public function testProcessBatchReplacesMismatchUpload(): void {
    $user = $this->createTestUser();
    $uid = (int) $user->id();
    $this->clearMismatchTempstore($uid);

    $media = $this->createDocumentMedia('report.pdf', 'original-content');
    $mid = (int) $media->id();
    $revision_before = (int) $media->getRevisionId();

    $upload_contents = 'replaced-mismatch-content';
    $stripped_filename = 'brand-new.pdf';
    $file = $this->createMismatchUploadFile($mid, 'brand-new', $upload_contents, 'bulk-replace-mismatch');
    $fid = (int) $file->id();
    $this->seedMismatchTempstore($uid, [$fid]);

    $context = [];
    ReplaceMismatchForm::processBatch($fid, 'tester', $context);

    $reloaded = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $this->assertNotNull($reloaded);
    $this->assertInstanceOf(MediaInterface::class, $reloaded);
    $this->assertGreaterThan($revision_before, (int) $reloaded->getRevisionId());

    $replaced = $reloaded->get('field_upload_file')->entity;
    $this->assertNotNull($replaced);
    $this->assertSame($stripped_filename, $replaced->getFilename());
    $this->assertStringStartsWith($this->expectedMediaFileDirectory($reloaded), $replaced->getFileUri());

    $real = \Drupal::service('file_system')->realpath($replaced->getFileUri());
    $this->assertNotFalse($real);
    $this->assertSame($upload_contents, (string) file_get_contents($real));

    $this->assertSame([], $this->bulkReplaceTempstore()->get($this->mismatchTempstoreKey($uid)));
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
