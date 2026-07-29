<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\file\Entity\File;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests document title synchronization between field_title and media name.
 */
class MediaDocumentTitleSyncTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Ensures save syncs media name when field_title changes.
   */
  public function testSyncsNameWhenFieldTitleChangesOnSave(): void {
    $destination = 'public://' . $this->randomMachineName(12) . '.txt';
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->copy($src, $destination, TRUE);

    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Original title',
      'field_title' => 'Original title',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
    ]);

    $media->set('field_title', 'Updated title');
    $media->save();

    $reloaded = \Drupal::entityTypeManager()->getStorage('media')->load($media->id());
    $this->assertSame('Updated title', (string) $reloaded->get('name')->value);
  }

}
