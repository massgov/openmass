<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\ExistingSite;

use Behat\Mink\Driver\GoutteDriver;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_utility\DebugCachability;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Verify media functionality.
 *
 * @group mass_media
 */
class MediaDownloadTest extends ExistingSiteBase {

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

    $driver = $this->getSession()->getDriver();
    // Disable redirection so we can capture the redirecting request.
    if ($driver instanceof GoutteDriver) {
      $driver->getClient()->followRedirects(FALSE);
    }

    (new DebugCachability())->requestDebugCachabilityHeaders($this->getSession());
    $this->visit($media->toUrl()->toString() . '/download');

    $location = $this->getSession()->getResponseHeader('Location');
    // Ensure that the redirect is properly formulated and that it uses the
    // url.site cache context.
    $this->assertEquals(file_create_url($file->getFileUri()), $location, 'Download URL is redirected to the file.');
    $this->assertStringContainsString('url.site', $this->getSession()->getResponseHeader('X-Drupal-Cache-Contexts'), 'url.site cache context is added to the response.');
  }

}
