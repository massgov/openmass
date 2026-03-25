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

}
