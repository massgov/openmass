<?php

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests moving files into and out of the private filesystem on media update.
 */
class MediaPrivateTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Media files are moved to private for new media draft.
   */
  public function testMovesFileToPrivateOnDraft() {
    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [$file],
    ]);
    array_pop($this->cleanupEntities);

    $media->setNewRevision();
    $media->set('moderation_state', MassModeration::DRAFT)->save();
    // Request cleanup after the switch to private has occurred.
    $this->markEntityForCleanup($media);

    $file2 = File::load($media->field_upload_file->target_id);
    $this->assertEquals('private', StreamWrapperManager::getScheme($file2->getFileUri()));

    // Make sure the original public file is gone. See mass_caching_file_move().
    $this->assertFileDoesNotExist($file->getFileUri());
  }

  /**
   * Media files are moved to private on media unpublish.
   *
   * The 'file can't de deleted' notices from this test come
   * from mass_utility_file_move().
   */
  public function testMovesFileToPrivateOnUnpublish() {
    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [$file],
    ]);
    array_pop($this->cleanupEntities);
    $media->setUnpublished()->set('moderation_state', 'unpublished')->save();
    // Request cleanup after the switch to private has occurred.
    $this->markEntityForCleanup($media);

    $file2 = File::load($media->field_upload_file->target_id);
    $this->assertEquals('private', StreamWrapperManager::getScheme($file2->getFileUri()));
  }

  /**
   * Media files are moved to public on media publish.
   *
   * The 'file can't de deleted' notices from this test come
   * from mass_utility_file_move().
   */
  public function testMovesFileToPublicOnPublish() {
    // Create a "Llama" media item.
    file_put_contents('private://llama-43.txt', 'Test');
    $file = File::create([
      'uri' => 'private://llama-43.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [$file],
      'status' => 0,
    ]);
    array_pop($this->cleanupEntities);
    $media->setPublished()->save();
    $this->markEntityForCleanup($media);

    $file2 = File::load($media->field_upload_file->target_id);
    $this->assertEquals('public', StreamWrapperManager::getScheme($file2->getFileUri()));
  }

}
