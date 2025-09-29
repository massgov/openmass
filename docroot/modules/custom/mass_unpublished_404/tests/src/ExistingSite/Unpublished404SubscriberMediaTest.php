<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_unpublished_404\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\media\MediaInterface;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * @group mass_unpublished_404
 *
 * Verifies 403→404 conversion for unpublished media (anon only).
 */
class Unpublished404SubscriberMediaTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  protected UserInterface $admin;

  protected MediaInterface $media;

  protected function setUp(): void {
    parent::setUp();

    // Admin (can view unpublished).
    $admin = $this->createUser();
    $admin->addRole('editor');
    $admin->activate();
    $admin->save();
    $this->admin = $admin;

    // Prepare a real file (use a core fixture) so media has a valid source field.
    $destination = 'public://unpub404-media-fixture.txt';
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $fs->copy('core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt', $destination, TRUE);

    // Create one reusable media entity via MediaCreationTrait.
    // Using the 'document' bundle and common field name `field_upload_file` as in Mass.gov.
    $this->media = $this->createMedia([
      'bundle' => 'document',
      'name' => 'Unpublished 404 Media',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 0,
      'moderation_state' => MassModeration::DRAFT,
    ]);
    $this->markEntityForCleanup($this->media);
    $this->markEntityForCleanup($file);
  }

  /**
   * Force the shared media into a specific state.
   *
   * Uses content moderation when present; falls back to status.
   */
  private function setMediaState(string $state): MediaInterface {
    $media = $this->media;
    if ($media->hasField('moderation_state')) {
      $media->set('moderation_state', $state);
      $media->set('status', $state === MassModeration::PUBLISHED ? 1 : 0);
    }
    $media->save();
    $this->media = $media;
    return $this->media;
  }

  private function mediaUrl(): string {
    return $this->media->toUrl('canonical')->toString();
  }

  /**
   * Ensure anon gets 404 when visiting unpublished media canonical page. */
  public function testAnonGets404OnUnpublishedMedia(): void {
    $this->setMediaState(MassModeration::DRAFT);

    $this->drupalGet($this->mediaUrl());
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Ensure anon sees 200 for published media. */
  public function testAnonSees200OnPublishedMedia(): void {
    $this->setMediaState(MassModeration::PUBLISHED);

    $this->drupalGet($this->mediaUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Ensure editor/admin can access unpublished media (200). */
  public function testEditorSees200OnUnpublishedMedia(): void {
    $this->setMediaState(MassModeration::DRAFT);

    $this->drupalLogin($this->admin);
    $this->drupalGet($this->mediaUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

}
