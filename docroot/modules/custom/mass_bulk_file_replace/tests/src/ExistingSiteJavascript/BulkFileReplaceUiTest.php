<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_bulk_file_replace\ExistingSiteJavascript;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\media\MediaInterface;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Browser tests for bulk file replace upload and mismatch verification UI.
 *
 * @group existing-site
 */
class BulkFileReplaceUiTest extends ExistingSiteSelenium2DriverTestBase {

  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->bulkReplaceUserId)) {
      $account = User::load($this->bulkReplaceUserId);
      if ($account) {
        \Drupal::currentUser()->setAccount($account);
      }
      $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
      $store->delete('mismatch_files_' . $this->bulkReplaceUserId);
    }
    parent::tearDown();
  }

  /**
   * User id for tempstore cleanup (set when a bulk-replace user is created).
   *
   * @var int|null
   */
  protected ?int $bulkReplaceUserId = NULL;

  /**
   * Creates an editor with bulk replace permission and logs in.
   */
  private function createBulkReplaceUser(): void {
    $user = $this->createUser([], 'bulk_replace_' . $this->randomMachineName(8));
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->markEntityForCleanup($user);
    $this->bulkReplaceUserId = (int) $user->id();
    $this->drupalLogin($user);
  }

  /**
   * Creates a document media item with an uploaded file.
   */
  private function createDocumentMediaWithFile(string $filename, string $contents = 'fixture'): MediaInterface {
    $destination = 'public://bulk-ui-test-' . $this->randomMachineName() . '-' . $filename;
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $fs->saveData($contents, $destination, FileExists::Replace);
    $file = File::create([
      'uri' => $destination,
      'filename' => $filename,
    ]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);

    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Bulk UI test ' . $this->randomMachineName(),
      'field_upload_file' => ['target_id' => $file->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->markEntityForCleanup($media);

    return $media;
  }

  /**
   * Seeds the mismatch review tempstore for the current user.
   *
   * @param int $uid
   *   User id that owns the tempstore namespace.
   * @param int[] $fids
   *   File entity ids.
   */
  private function seedMismatchTempstore(int $uid, array $fids): void {
    $account = User::load($uid);
    if ($account) {
      \Drupal::currentUser()->setAccount($account);
    }
    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $store->set('mismatch_files_' . $uid, $fids);
  }

  /**
   * Programmatically feeds a local file into the Dropzone instance.
   */
  private function dropFileToDropzone(string $absolutePath): void {
    $session = $this->getSession();
    $session->executeScript(
      <<<'JS'
(function () {
  var existing = document.querySelector('input[name="fakebulkfile"]');
  if (existing) {
    existing.remove();
  }
  var inp = document.createElement('input');
  inp.type = 'file';
  inp.name = 'fakebulkfile';
  document.body.appendChild(inp);
})();
JS
    );
    $session->getPage()->attachFileToField('fakebulkfile', $absolutePath);
    $result = $session->evaluateScript(
      <<<'JS'
(function () {
  var input = document.querySelector('input[name=fakebulkfile]');
  if (!input || !input.files || !input.files[0]) {
    return 'no-file';
  }
  var el = document.querySelector('.dropzone-enable');
  if (!el || !el.dropzone) {
    return 'no-dropzone';
  }
  el.dropzone.addFile(input.files[0]);
  return 'ok';
})()
JS
    );
    $this->assertSame('ok', $result, 'Dropzone addFile should run in the browser.');
  }

  /**
   * Writes a disk file for upload testing.
   */
  private function writeDiskFile(string $absolutePath, string $contents): void {
    $dir = dirname($absolutePath);
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }
    file_put_contents($absolutePath, $contents);
    $this->assertFileExists($absolutePath);
  }

  /**
   * Ensures the temporary mismatch upload directory exists in all environments.
   */
  private function ensureMismatchTempDirectory(): void {
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $flags = FileSystemInterface::CREATE_DIRECTORY
      | FileSystemInterface::MODIFY_PERMISSIONS;
    $directory = 'temporary://mass_bulk_file_replace';
    $fs->prepareDirectory($directory, $flags);
  }

  /**
   * Upload form shows accessibility radios and dropzone.
   */
  public function testUploadFormRendersAccessibilityRadios(): void {
    $this->createBulkReplaceUser();
    $this->drupalGet('admin/ma-dash/download-documents/replace');
    $this->assertSession()->pageTextContains('Bulk Replace Files');
    $this->getSession()->wait(10000, "document.querySelector('.dropzone-enable') !== null");
    $this->assertSession()->elementExists('css', '.dropzone-enable');
    $this->assertSession()->elementExists('css', 'input[name="accessibility_status"][value="verified_accessible"]');
    $this->assertSession()->elementExists('css', 'input[name="accessibility_status"][value="unverified_or_not_accessible_yet"]');
    $this->assertSession()->elementExists('css', 'input[name="accessibility_status"][value="exception"]');
    $this->assertSession()->buttonExists('Start replacement');
  }

  /**
   * Exact filename match replaces via upload widget and skips mismatch page.
   */
  public function testUploadAutoReplacesOnExactFilename(): void {
    $this->createBulkReplaceUser();
    $uid = $this->bulkReplaceUserId;
    $this->assertNotNull($uid);
    $account = User::load($uid);
    if ($account) {
      \Drupal::currentUser()->setAccount($account);
    }
    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $store->delete('mismatch_files_' . $uid);

    $media = $this->createDocumentMediaWithFile('housing-proposal.pdf', 'before');
    $mid = (int) $media->id();
    // Basename must match token + id; Dropzone posts this name to the server.
    $upload_basename = 'housing-proposal_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf';
    $dir = sys_get_temp_dir() . '/bulk-replace-ui-' . $this->randomMachineName();
    mkdir($dir, 0777, TRUE);
    $disk_path = $dir . '/' . $upload_basename;
    $this->writeDiskFile($disk_path, 'after-replace');

    $this->drupalGet('admin/ma-dash/download-documents/replace');
    $this->getSession()->wait(10000, "document.querySelector('.dropzone-enable') !== null");
    $this->getSession()->getPage()->selectFieldOption('accessibility_status', 'verified_accessible');

    $this->dropFileToDropzone($disk_path);

    $this->getSession()->wait(60000, "document.querySelector('.dz-success') !== null");
    $this->assertSession()->elementExists('css', '.dz-success');
    // Allow Dropzone to finish posting the file to the server.
    $this->getSession()->wait(5000);

    $this->getSession()->getPage()->pressButton('Start replacement');
    $wait_js = "document.body && (document.body.innerText.indexOf(" .
      "'All uploaded files were successfully replaced.') !== -1 || " .
      "document.body.innerText.indexOf('Some uploaded files need') !== -1 || " .
      "document.body.innerText.indexOf('No files were uploaded') !== -1)";
    $this->getSession()->wait(120000, $wait_js);

    $page_text = $this->getSession()->getPage()->getText();
    $this->assertStringNotContainsString('No files were uploaded', $page_text);

    // Primary assertion: file entity updated after batch (avoids fragile status messages).
    \Drupal::entityTypeManager()->getStorage('media')->resetCache();
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($mid);
    $this->assertNotNull($media);
    $this->assertInstanceOf(MediaInterface::class, $media);
    $file = $media->get('field_upload_file')->entity;
    $this->assertNotNull($file);
    $this->assertSame('housing-proposal.pdf', $file->getFilename());
    $real = \Drupal::service('file_system')->realpath($file->getFileUri());
    $this->assertNotFalse($real);
    $this->assertStringContainsString('after-replace', (string) file_get_contents($real));

    $this->assertStringNotContainsString(
      'Review Mismatched File Replacements',
      $page_text
    );
    $path = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH) ?? '';
    $this->assertStringEndsWith('/admin/ma-dash/download-documents/replace', $path);
  }

  /**
   * Mismatch page hides media ID tokens and disables rows without a match.
   */
  public function testMismatchPageHidesTokenAndDisablesNoMatchRow(): void {
    $this->createBulkReplaceUser();
    $uid = (int) $this->bulkReplaceUserId;

    $media = $this->createDocumentMediaWithFile('report.pdf');
    $mid = (int) $media->id();

    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $this->ensureMismatchTempDirectory();
    $uri_token = 'temporary://mass_bulk_file_replace/bulk-ui-token-' . $this->randomMachineName() . '.pdf';
    $fs->saveData('token-upload', $uri_token, FileExists::Replace);
    $file_token = File::create([
      'uri' => $uri_token,
      'filename' => 'brand-new_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf',
      'status' => 0,
    ]);
    $file_token->save();
    $this->markEntityForCleanup($file_token);

    $uri_orphan = 'temporary://mass_bulk_file_replace/bulk-ui-orphan-' . $this->randomMachineName() . '.pdf';
    $fs->saveData('orphan-upload', $uri_orphan, FileExists::Replace);
    $file_orphan = File::create([
      'uri' => $uri_orphan,
      'filename' => 'orphan.pdf',
      'status' => 0,
    ]);
    $file_orphan->save();
    $this->markEntityForCleanup($file_orphan);

    $this->seedMismatchTempstore($uid, [(int) $file_token->id(), (int) $file_orphan->id()]);

    $this->drupalGet('admin/ma-dash/download-documents/mismatch');

    $this->assertSession()->pageTextNotContains('DO_NOT_CHANGE_THIS_MEDIA_ID_');
    $this->assertSession()->pageTextContains('brand-new.pdf');

    $page_text = $this->getSession()->getPage()->getText();
    $this->assertStringContainsString('brand-new.pdf', $page_text);
    $this->assertStringContainsString('No match', $page_text);

    $this->assertSession()->elementExists('xpath', '//table//tr[contains(., "orphan.pdf")]//input[@type="checkbox"][@disabled]');
    $this->assertSession()->elementExists('xpath', '//table//tr[contains(., "brand-new.pdf")]//input[@type="checkbox"][not(@disabled)]');
    $checked = $this->getSession()->evaluateScript(<<<'JS'
(function () {
  var row = document.evaluate("//table//tr[contains(., 'brand-new.pdf')]", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
  if (!row) {
    return false;
  }
  var cb = row.querySelector('input[type="checkbox"]:not([disabled])');
  return !!(cb && cb.checked);
})()
JS
    );
    $this->assertTrue((bool) $checked, 'Matched mismatch row is selected by default.');
  }

  /**
   * Cancel clears mismatch tempstore and returns to the upload form.
   */
  public function testCancelClearsMismatchTempstore(): void {
    $this->createBulkReplaceUser();
    $uid = (int) $this->bulkReplaceUserId;

    $media = $this->createDocumentMediaWithFile('report-cancel.pdf');
    $mid = (int) $media->id();

    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $this->ensureMismatchTempDirectory();
    $uri_token = 'temporary://mass_bulk_file_replace/bulk-ui-cancel-' . $this->randomMachineName() . '.pdf';
    $fs->saveData('x', $uri_token, FileExists::Replace);
    $file_token = File::create([
      'uri' => $uri_token,
      'filename' => 'diff_DO_NOT_CHANGE_THIS_MEDIA_ID_' . $mid . '.pdf',
      'status' => 0,
    ]);
    $file_token->save();
    $this->markEntityForCleanup($file_token);

    $this->seedMismatchTempstore($uid, [(int) $file_token->id()]);

    $this->drupalGet('admin/ma-dash/download-documents/mismatch');
    $this->getSession()->getPage()->pressButton('Cancel');

    $this->getSession()->wait(10000, "window.location.pathname.indexOf('/admin/ma-dash/download-documents/replace') !== -1");
    $this->assertStringEndsWith('/admin/ma-dash/download-documents/replace', parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH) ?? '');

    $account = User::load($uid);
    $this->assertNotNull($account);
    \Drupal::currentUser()->setAccount($account);
    $store = \Drupal::service('tempstore.private')->get('mass_bulk_file_replace');
    $this->assertNull($store->get('mismatch_files_' . $uid));
  }

}
