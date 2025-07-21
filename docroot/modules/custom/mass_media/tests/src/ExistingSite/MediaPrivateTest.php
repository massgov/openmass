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

  /**
   * Test: saving draft keeps file public if default is still published.
   *
   * Saving a draft while keeping the published version published
   * does not move the file to private.
   */
  public function testSavingDraftKeepsFilePublicIfDefaultRevisionIsPublished() {
    // Create a "Llama" media item and publish it.
    file_put_contents('public://llama-44.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-44.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Llama Public Draft',
      'bundle' => 'document',
      'field_upload_file' => [$file],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $media->save();
    array_pop($this->cleanupEntities);

    // Save a new draft revision without unpublishing the default one.
    $media->setNewRevision();
    $media->set('moderation_state', MassModeration::DRAFT)->save();
    $this->markEntityForCleanup($media);

    // Reload the file and ensure it remains public.
    $file2 = File::load($media->field_upload_file->target_id);
    $this->assertEquals('public', StreamWrapperManager::getScheme($file2->getFileUri()));
    $this->assertFileExists($file2->getFileUri());
  }

  /**
   * Test: draft file moved to private, original file stays public.
   *
   * Replacing the file in a draft revision moves the new file to private,
   * while the original file remains public since the published revision still uses it.
   */
  public function testDraftWithNewFileMovesToPrivateAndKeepsOriginalPublic() {
    // Create and publish a media item with the original file.
    file_put_contents('public://llama-original.txt', 'Original');
    $original_file = File::create([
      'uri' => 'public://llama-original.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Llama Replace Test',
      'bundle' => 'document',
      'field_upload_file' => [$original_file],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $media->save();
    array_pop($this->cleanupEntities);

    // Save a new draft revision with a different file.
    file_put_contents('public://llama-draft.txt', 'Draft');
    $draft_file = File::create([
      'uri' => 'public://llama-draft.txt',
    ]);
    $media->setNewRevision();
    $media->set('moderation_state', MassModeration::DRAFT);
    $media->set('field_upload_file', [$draft_file]);
    $media->save();
    $this->markEntityForCleanup($media);

    // Reload both files.
    $original_file = File::load($original_file->id());
    $draft_file = File::load($media->field_upload_file->target_id);

    // The original file should remain public.
    $this->assertEquals('public', StreamWrapperManager::getScheme($original_file->getFileUri()));
    $this->assertFileExists($original_file->getFileUri());

    // The new draft file should be private.
    $this->assertEquals('private', StreamWrapperManager::getScheme($draft_file->getFileUri()));
    $this->assertFileExists($draft_file->getFileUri());
  }

}
