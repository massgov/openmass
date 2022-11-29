<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests add media to collections bulk.
 */
class AddDocsCollectionsViewTest extends ExistingSiteBase {

  use LoginTrait;
  use MediaCreationTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
  }

  /**
   * Create media file.
   */
  private function createMediaFile() {
    // Create a file to upload.
    $destination = 'public://llama-23.txt';
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    // Create a media item.
    return $this->createMedia([
      'field_title' => 'docsmediabulkllamatest',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Run through test steps.
   */
  public function testDocsToCollectionsBulk() {
    $checked = [];
    $mids = [];

    // Create 3 media items to bulk add collections later.
    for ($x = 0; $x < 3; $x++) {
      $media = $this->createMediaFile();
      // Store media ids to check the result later.
      $mids[] = $media->id();
      // Generate an array of values to submit the form.
      $checked["views_bulk_operations_bulk_form[$x]"] = TRUE;
    }
    $this->drupalGet('admin/ma-dash/reports/add-collections-documents');

    // Trigger search to get some results.
    $this->submitForm(['field_title_value' => 'docsmediabulkllamatest'], $this->t('Search'), 'views-exposed-form-add-collections-documents-media-page-list');
    // Trigger the action.
    $this->submitForm($checked, $this->t('Add documents to a collection'), 'views-form-add-collections-documents-media-page-list');

    // Check if the "New Collection" element exists.
    $this->assertSession()->pageTextContains('New Collection');
    $this->assertSession()->elementExists('css', '.field--widget-term-reference-tree');

    // Use "Test 2 (87956)" collection and trigger the batch.
    $this->submitForm(['new_collection[0][87861][87861-children][87956][87956]' => TRUE], $this->t('Add collections'), 'views-bulk-operations-configure-action');
    // Trigger the batch process.
    $this->submitForm([], $this->t('Execute action'), 'views-bulk-operations-confirm-action');
    $page = $this->getSession()->getPage();

    // Wait for the batch to finish processing.
    $page->waitFor(3, function () use ($page) {
      return $page->hasContent('Your changes have been successfully made.');
    });

    // Go to each media item and verify the value is set correctly.
    foreach ($mids as $mid) {
      $this->drupalGet("media/$mid/edit");
      $page = $this->getSession()->getPage();
      // Verify the value of the "Collections" field.
      $this->assertEquals($page->find('css', '#edit-field-collections-0-target-id')->getValue(), 'Test 2 (87956)');
    }
  }

}
