<?php

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\ConfigTrait;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Verify media functionality.
 *
 * @group mass_media
 */
class MediaDownloadTest extends MassExistingSiteBase {

  use ConfigTrait;
  use MediaCreationTrait;

  /**
   * Ensure that a request to media/$ID/download serves the file.
   */
  public function testMediaDownload() {
    // Create a file to upload.
    $destination = 'public://llama-23-' . $this->randomMachineName() . '.txt';
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
    $this->markEntityForCleanup($file);

    $this->visit($media->toUrl()->toString() . '/download');
    $expected_path = $media->toUrl()->toString() . '/download';
    $this->assertStringContainsString($expected_path, $this->getSession()->getCurrentUrl());

    $content_type = $this->getSession()->getResponseHeader('Content-Type');
    $this->assertNotEmpty($content_type);
    $this->assertStringContainsString('text/plain', $content_type);

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('inline', $disposition);

    $cache_control = $this->getSession()->getResponseHeader('Cache-Control');
    $this->assertNotEmpty($cache_control);
    $this->assertStringContainsString('max-age=60', $cache_control);
    $this->assertStringContainsString('public', $cache_control);

    $last_modified = $this->getSession()->getResponseHeader('Last-Modified');
    $this->assertNotEmpty($last_modified);

    $etag = $this->getSession()->getResponseHeader('ETag');
    $this->assertNotEmpty($etag);
  }

  /**
   * Public downloads use the file entity referenced by the media item.
   */
  public function testMediaDownloadWithDuplicateFileUri(): void {
    $destination = 'public://llama-download-duplicate-' . $this->randomMachineName() . '.txt';
    file_put_contents($destination, 'DUPLICATE URI BYTES');

    // Create an older, unreferenced file entity sharing the same URI. Core's
    // file_download hook will load this entity first and return no headers.
    $older_file = File::create([
      'uri' => $destination,
    ]);
    $older_file->setPermanent();
    $older_file->save();

    $referenced_file = File::create([
      'uri' => $destination,
    ]);
    $referenced_file->setPermanent();
    $referenced_file->save();

    $media = $this->createMedia([
      'title' => 'Llama duplicate URI',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $referenced_file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->markEntityForCleanup($older_file);
    $this->markEntityForCleanup($referenced_file);

    $content = $this->drupalGet(ltrim($media->toUrl()->toString() . '/download', '/'));

    $this->assertSame(200, $this->getSession()->getStatusCode());
    $this->assertStringContainsString('DUPLICATE URI BYTES', $content);
    $this->assertStringContainsString('text/plain', $this->getSession()->getResponseHeader('Content-Type'));
  }

  /**
   * PDF downloads should display inline in the browser by default.
   */
  public function testMediaDownloadPdfServesInlineDisposition(): void {
    $destination = 'public://llama-download-' . $this->randomMachineName() . '.pdf';
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
    $this->markEntityForCleanup($file);

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
    $destination = 'public://llama-download-' . $this->randomMachineName() . '.zip';
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
    $this->markEntityForCleanup($file);

    $this->visit($media->toUrl()->toString() . '/download');

    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertNotEmpty($disposition);
    $this->assertStringContainsString('attachment', $disposition);
  }

  /**
   * The attachment query parameter should force a download.
   */
  public function testMediaDownloadAttachmentQueryParam(): void {
    $destination = 'public://llama-download-attachment-' . $this->randomMachineName() . '.pdf';
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
    $this->markEntityForCleanup($file);

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
    $destination1 = 'public://llama-download-v1-' . $this->randomMachineName() . '.txt';
    file_put_contents($destination1, 'Version 1');
    $file1 = File::create([
      'uri' => $destination1,
    ]);
    $file1->setPermanent();
    $file1->save();

    // v2 file.
    $destination2 = 'public://llama-download-v2-' . $this->randomMachineName() . '.txt';
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

    // First request should return v1 bytes and cache validators.
    $content_v1 = $this->drupalGet($download_path);
    $this->assertStringContainsString('Version 1', $content_v1);
    $etag_v1 = $this->getSession()->getResponseHeader('ETag');
    $last_modified_v1 = $this->getSession()->getResponseHeader('Last-Modified');
    $this->assertNotEmpty($etag_v1);
    $this->assertNotEmpty($last_modified_v1);

    // Replace the file reference and create a new revision while staying
    // published. The controller should serve the new file bytes and Drupal
    // cache should not keep serving the old response body.
    $media->set('field_upload_file', [
      'target_id' => $file2->id(),
    ]);
    $media->setNewRevision();
    $media->set('moderation_state', MassModeration::PUBLISHED);
    $media->save();

    // Reload file1 because the media update moves it to private storage.
    $file1 = File::load($file1->id());
    $this->assertNotNull($file1);
    $this->markEntityForCleanup($file1);
    $this->markEntityForCleanup($file2);

    $content_v2 = $this->drupalGet($download_path);
    $this->assertStringContainsString('Version 2', $content_v2);
    $this->assertStringNotContainsString('Version 1', $content_v2);

    $etag_v2 = $this->getSession()->getResponseHeader('ETag');
    $last_modified_v2 = $this->getSession()->getResponseHeader('Last-Modified');
    $this->assertNotEmpty($etag_v2);
    $this->assertNotEmpty($last_modified_v2);
    $this->assertNotEquals($etag_v1, $etag_v2, 'ETag must change when the underlying file is replaced.');
  }

  /**
   * Public downloads expose Edge-Cache-Tag headers for invalidation.
   */
  public function testMediaDownloadExposesEdgeCacheTags(): void {
    if (!\Drupal::moduleHandler()->moduleExists('akamai')) {
      $this->markTestSkipped('The akamai module is not enabled.');
    }

    $this->setConfigValues([
      'akamai.settings' => [
        'edge_cache_tag_header' => TRUE,
        'edge_cache_tag_header_blacklist' => [],
      ],
    ]);
    $this->container->get('config.factory')->clearStaticCache();

    try {
      $destination = 'public://llama-download-cache-tags-' . $this->randomMachineName() . '.txt';
      file_put_contents($destination, 'Cache tag test');
      $file = File::create([
        'uri' => $destination,
      ]);
      $file->setPermanent();
      $file->save();

      $media = $this->createMedia([
        'title' => 'Llama cache tags',
        'bundle' => 'document',
        'field_upload_file' => [
          'target_id' => $file->id(),
        ],
        'status' => 1,
        'moderation_state' => MassModeration::PUBLISHED,
      ]);
      $this->markEntityForCleanup($file);

      $this->visit($media->toUrl()->toString() . '/download');

      $edge_cache_tag = $this->getSession()->getResponseHeader('Edge-Cache-Tag');
      $this->assertNotEmpty($edge_cache_tag);
      $this->assertStringNotContainsString(' ', $edge_cache_tag);
    }
    finally {
      $this->restoreConfigValues();
      $this->container->get('config.factory')->clearStaticCache();
    }
  }

  /**
   * Unpublished documents move their file to private storage.
   *
   * Anonymous users must not be able to download those bytes.
   */
  public function testMediaDownloadPrivateFileDeniedForUnpublishedDocument(): void {
    $destination = 'public://llama-download-private-unpublished-' . $this->randomMachineName() . '.txt';
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
    $this->markEntityForCleanup($unpublished_file);
    $this->assertEquals('private', StreamWrapperManager::getScheme($unpublished_file->getFileUri()));

    $this->visit($media->toUrl()->toString() . '/download');

    $this->assertNotEquals(200, $this->getSession()->getStatusCode());
    $this->assertStringNotContainsString('UNPUBLISHED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
  }

  /**
   * Restricted documents should be viewable only by their owner.
   *
   * Anonymous users must not be able to download private files for those
   * documents.
   */
  public function testMediaDownloadPrivateFileDeniedForRestrictedDocument(): void {
    // Login as author so we can create a restricted media owned by them.
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    $destination = 'private://llama-download-private-restricted-' . $this->randomMachineName() . '.txt';
    file_put_contents($destination, 'RESTRICTED PRIVATE BYTES');
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'title' => 'Restricted document download',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => 'restricted',
    ]);
    $this->markEntityForCleanup($file);

    $this->drupalLogout();

    $this->visit($media->toUrl()->toString() . '/download');

    $this->assertNotEquals(200, $this->getSession()->getStatusCode());
    $this->assertStringNotContainsString('RESTRICTED PRIVATE BYTES', $this->getSession()->getPage()->getContent());
  }

}
