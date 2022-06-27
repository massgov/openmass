<?php

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Verify media functionality.
 *
 * @group mass_media
 */
class MediaRevisionTest extends ExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Ensure that a document is no longer available after it is replaced.
   *
   * @see mass_media_media_update()
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaRevision() {
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
    ]);

    $this->visit('admin/ma-dash/documents');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $this->getCurrentPage()->selectFieldOption('action', 'Moderation: Unpublish media');
    $this->getCurrentPage()->checkField('views_bulk_operations_bulk_form[0]');
    $this->getCurrentPage()->pressButton('Apply to selected items');
    $this->assertSession()->pageTextContains('Execute action');
    $this->getCurrentPage()->pressButton('Execute action');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $this->assertSession()->pageTextContains('Your changes have been successfully made.');
  }

}
