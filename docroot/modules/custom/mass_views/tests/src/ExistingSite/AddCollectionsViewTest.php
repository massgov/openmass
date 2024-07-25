<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests add media to collections bulk.
 */
class AddCollectionsViewTest extends MassExistingSiteBase {

  use LoginTrait;
  use MediaCreationTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
    $this->submitForm(['field_title_value' => 'docsmediabulkllamatest'], (string) $this->t('Search'), 'views-exposed-form-add-collections-documents-media-page-list');
    // Trigger the action.
    $this->submitForm($checked, (string) $this->t('Add documents to a collection'), 'views-form-add-collections-documents-media-page-list');

    // Check if the "New Collection" element exists.
    $this->assertSession()->pageTextContains('New Collection');
    $this->assertSession()->elementExists('css', '.field--widget-term-reference-tree');

    // Use "Test 2 (87956)" collection and trigger the batch.
    $this->submitForm(['new_collection[0][87861][87861-children][87956][87956]' => TRUE], (string) $this->t('Add collections'), 'views-bulk-operations-configure-action');
    // Trigger the batch process.
    $this->submitForm([], (string) $this->t('Execute action'), 'views-bulk-operations-confirm-action');
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

  /**
   * Run through test steps.
   */
  public function testChangeCollectionsBulk() {
    $checked = [];
    $nids = [];

    // Create 3 node items to bulk add collections later.
    for ($x = 0; $x < 3; $x++) {
      // Adding one node, to show one result.
      $node = $this->createNode([
        'type' => 'service_page',
        'title' => 'bulktestcollections',
        'moderation_state' => MassModeration::PUBLISHED,
      ]);
      // Store node ids to check the result later.
      $nids[] = $node->id();
      // Generate an array of values to submit the form.
      $checked["views_bulk_operations_bulk_form[$x]"] = TRUE;
    }
    $this->drupalGet('admin/ma-dash/reports/change-collections');

    // Trigger search to get some results.
    $this->submitForm(['title' => 'bulktestcollections'], (string) $this->t('Apply'), 'views-exposed-form-change-collections-page-1');
    // Trigger the action.
    $this->submitForm($checked, (string) $this->t('Add Collections'), 'views-form-change-collections-page-1');

    // Check if the "New Collection" element exists.
    $this->assertSession()->pageTextContains('New Collection');
    $this->assertSession()->elementExists('css', '.field--widget-term-reference-tree');

    // Use "Test 2 (87956)" collection and trigger the batch.
    $this->submitForm(['new_collection[0][87861][87861-children][87956][87956]' => TRUE], (string) $this->t('Add collections'), 'views-bulk-operations-configure-action');
    // Trigger the batch process.
    $this->submitForm([], (string) $this->t('Execute action'), 'views-bulk-operations-confirm-action');
    $page = $this->getSession()->getPage();

    // Wait for the batch to finish processing.
    $page->waitFor(3, function () use ($page) {
      return $page->hasContent('Your changes have been successfully made.');
    });

    // Go to each node item and verify the value is set correctly.
    foreach ($nids as $nid) {
      $this->drupalGet("node/$nid/edit");
      $page = $this->getSession()->getPage();
      // Verify the value of the "Collections" field.
      $this->assertEquals($page->find('css', '#edit-field-collections-0-target-id')->getValue(), 'Test 2 (87956)');
    }
  }

}
