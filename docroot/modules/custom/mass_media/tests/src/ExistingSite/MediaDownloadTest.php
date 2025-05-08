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
   * Ensure that a request to media/$ID/download redirects to the file.
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

    $this->getSession()->getDriver()->getClient()->followRedirects(FALSE);
    $this->getSession()->getDriver()->getClient()->followMetaRefresh(FALSE);
    $this->getSession()->getDriver()->visit($media->toUrl()->toString() . '/download');
    $this->assertSession()->statusCodeEquals(301);
    $this->assertSession()->responseHeaderContains('Cache-control', 'max-age=1800, public, stale-if-error=604800, stale-while-revalidate=604800');

    $this->getSession()->getDriver()->getClient()->followRedirects(TRUE);
    $this->getSession()->getDriver()->getClient()->followMetaRefresh(TRUE);
    $this->drupalGet($media->toUrl()->toString() . '/download');
    $this->assertEquals(\Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()), $this->getSession()->getCurrentUrl());
    $this->assertEquals('text/plain', $this->getSession()->getResponseHeader('Content-Type'), 'url.site cache context is added to the response.');
  }

}
