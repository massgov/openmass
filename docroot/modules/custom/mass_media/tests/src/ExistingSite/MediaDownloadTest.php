<?php

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Verify media functionality.
 *
 * @group mass_media
 */
class MediaDownloadTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Ensure that a request to media/$ID/download serves the file.
   */
  public function testMediaDownload() {
    // Create a file to upload.
    $destination = 'public://llama-23.txt';
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();
    // Nothing copied the file so we do so.
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->copy($src, $destination, TRUE);

    // Create a "Llama" media item.
    $media = $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($media->toUrl()->toString() . '/download');
    $expected_path = $media->toUrl()->toString() . '/download';
    $this->assertStringContainsString($expected_path, $this->getSession()->getCurrentUrl());

    $content_type = $this->getSession()->getResponseHeader('Content-Type');
    $this->assertNotEmpty($content_type);
    $this->assertStringContainsString('text/plain', $content_type);

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('inline', $disposition);
  }

  /**
   * PDF downloads should display inline in the browser by default.
   */
  public function testMediaDownloadPdfServesInlineDisposition(): void {
    $destination = 'public://llama-download.pdf';
    file_put_contents($destination, '%PDF-1.4 llama');
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'title' => 'Llama PDF',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($media->toUrl()->toString() . '/download');

    $content_type = $this->getSession()->getResponseHeader('Content-Type');
    $this->assertNotEmpty($content_type);
    $this->assertStringContainsString('application/pdf', $content_type);

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('inline', $disposition);
    $this->assertStringNotContainsString('attachment', $disposition);
  }

  /**
   * Non-viewable file types should download by default.
   */
  public function testMediaDownloadZipServesAttachmentDisposition(): void {
    $destination = 'public://llama-download.zip';
    file_put_contents($destination, 'PK llama');
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'title' => 'Llama ZIP',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($media->toUrl()->toString() . '/download');

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('attachment', $disposition);
  }

  /**
   * The attachment query parameter should force a download.
   */
  public function testMediaDownloadAttachmentQueryParam(): void {
    $destination = 'public://llama-download-attachment.pdf';
    file_put_contents($destination, '%PDF-1.4 llama');
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'title' => 'Llama PDF attachment',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($media->toUrl()->toString() . '/download?attachment');

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('attachment', $disposition);
  }

  /**
   * Test file replacement.
   *
   * If the underlying media file is replaced, /download should serve
   * the new bytes (not a stale cached response).
   */
  public function testMediaDownloadServesUpdatedFileAfterReplacement() {
    // v1 file.
    $destination1 = 'public://llama-download-v1.txt';
    file_put_contents($destination1, 'Version 1');
    $file1 = File::create([
      'uri' => $destination1,
    ]);
    $file1->setPermanent();
    $file1->save();

    // v2 file.
    $destination2 = 'public://llama-download-v2.txt';
    file_put_contents($destination2, 'Version 2');
    $file2 = File::create([
      'uri' => $destination2,
    ]);
    $file2->setPermanent();
    $file2->save();

    // Create a published document media entity pointing to v1.
    $media = $this->createMedia([
      'title' => 'Llama Download Cache',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file1->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $download_path = ltrim($media->toUrl()->toString() . '/download', '/');

    // First request should return v1 bytes.
    $content_v1 = $this->drupalGet($download_path);
    $this->assertStringContainsString('Version 1', $content_v1);

    // Replace the file reference and create a new revision while staying
    // published. The controller should serve the new file bytes and Drupal
    // cache should not keep serving the old response body.
    $media->set('field_upload_file', [
      'target_id' => $file2->id(),
    ]);
    $media->setNewRevision();
    $media->set('moderation_state', MassModeration::PUBLISHED);
    $media->save();

    $content_v2 = $this->drupalGet($download_path);
    $this->assertStringContainsString('Version 2', $content_v2);
    $this->assertStringNotContainsString('Version 1', $content_v2);
  }

  /**
   * Unpublished documents move their file to private storage.
   *
   * Anonymous users must not be able to download those bytes.
   */
  public function testMediaDownloadPrivateFileDeniedForUnpublishedDocument(): void {
    $destination = 'public://llama-download-private-unpublished.txt';
    file_put_contents($destination, 'UNPUBLISHED PRIVATE BYTES');
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    // Create an unpublished document; mass_media_presave should move the
    // uploaded file to private://.
    $media = $this->createMedia([
      'title' => 'Unpublished document download',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 0,
      'moderation_state' => 'unpublished',
    ]);

    $unpublished_file = File::load($media->field_upload_file->target_id);
    $this->assertNotNull($unpublished_file);
    $this->assertEquals('private', \Drupal\Core\StreamWrapper\StreamWrapperManager::getScheme($unpublished_file->getFileUri()));

    $this->visit($media->toUrl()->toString() . '/download');

    // Pin the exact denial code instead of "anything but 200": a 500 (controller
    // fatal) would also satisfy assertNotEquals() and silently mask a broken
    // access contract. Access to an unpublished document is denied at the route
    // (_entity_access: media.view); Mass.gov surfaces that denial as a 404 to
    // avoid disclosing that the document exists, rather than a bare 403.
    $this->assertEquals(404, $this->getSession()->getStatusCode());
    $this->assertStringNotContainsString('UNPUBLISHED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
  }

  /**
   * Restricted documents are downloadable only by their (non-admin) owner.
   *
   * Exercises the actual access contract instead of relying on an incidental
   * core "-1" for any private file: the owning author gets the bytes (200),
   * while a different authenticated non-admin user and anonymous users are
   * denied. The owner must NOT be an administrator, because
   * mass_media_media_access() lets administrators bypass the restriction, which
   * would make an admin owner unable to prove the owner-only path.
   *
   * A "restricted" moderation state is unpublished (status 0) with its file
   * kept in private://, so the owner needs "view own unpublished media" to read
   * it. We grant that via the existing "author" role ("download media" comes
   * from the authenticated role) rather than a freshly created role: in an
   * ExistingSite test the web server serving the request does not reliably see
   * the permissions of a role created moments earlier in the test process.
   * Denials surface as 404 (Mass.gov hides the existence of the document), not
   * a bare 403.
   */
  public function testMediaDownloadRestrictedDocumentOwnerOnly(): void {
    // Non-admin author who owns the restricted document.
    $owner = $this->createUser();
    $owner->addRole('author');
    $owner->save();
    // A different non-admin user with the same role but who is not the owner.
    $other = $this->createUser();
    $other->addRole('author');
    $other->save();

    $destination = 'private://llama-download-private-restricted.txt';
    file_put_contents($destination, 'RESTRICTED PRIVATE BYTES');
    $file = File::create([
      'uri' => $destination,
      'uid' => $owner->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'title' => 'Restricted document download',
      'bundle' => 'document',
      'uid' => $owner->id(),
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => 'restricted',
    ]);

    $download_url = $media->toUrl()->toString() . '/download';

    // Owner: allowed, receives the actual file bytes.
    $this->drupalLogin($owner);
    $this->visit($download_url);
    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertStringContainsString('RESTRICTED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
    $this->drupalLogout();

    // Different authenticated non-admin user: denied (not the owner).
    $this->drupalLogin($other);
    $this->visit($download_url);
    $this->assertEquals(404, $this->getSession()->getStatusCode());
    $this->assertStringNotContainsString('RESTRICTED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
    $this->drupalLogout();

    // Anonymous user: denied.
    $this->visit($download_url);
    $this->assertEquals(404, $this->getSession()->getStatusCode());
    $this->assertStringNotContainsString('RESTRICTED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
  }

}
