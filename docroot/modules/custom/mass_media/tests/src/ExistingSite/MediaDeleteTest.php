<?php

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\LoginTrait\LoginTrait;

/**
 * Verify media functionality.
 *
 * @group mass_media
 */
class MediaDeleteTest extends MassExistingSiteBase {

  use MediaCreationTrait;
  use LoginTrait;

  private UserInterface $admin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // An admin is needed.
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->admin = $admin;
  }

  /**
   * Ensure that a document is no longer available after it is replaced.
   *
   * @see mass_media_media_update()
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaDelete() {
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

    $this->visit($file->createFileUrl());
    $this->assertEquals($this->getSession()->getStatusCode(), 200);

    // Now replace that file.
    $destination = 'public://llama-42.txt';
    $file2 = File::create([
      'uri' => $destination,
    ]);
    $file2->setPermanent();
    $file2->save();
    $this->markEntityForCleanup($file2);
    // Nothing copied the file so we do so.
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-42.txt';
    $file_system->copy($src, $destination, TRUE);

    // Update the "Llama" media item.
    $media->field_upload_file->target_id = $file2->id();
    $media->save();
    // The original file is now unavailable to anon.
    $this->visit($file->createFileUrl());
    $this->assertEquals($this->getSession()->getStatusCode(), 404);

    // Make sure original file was moved to private filesystem
    $file = File::load($file->id());
    $this->drupalLogin($this->admin);
    $this->visit($file->createFileUrl());
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
  }

}
