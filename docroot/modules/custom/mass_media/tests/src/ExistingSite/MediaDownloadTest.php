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

}
