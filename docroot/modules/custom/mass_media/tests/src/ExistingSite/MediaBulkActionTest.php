<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_media\ExistingSite;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests "All Content" view requires input to show content to speed up login.
 */
class MediaBulkActionTest extends MassExistingSiteBase {
  const PUBLISH_ACTION = '3';
  const RESTRICT_ACTION = '4';
  const TRASH_ACTION = '5';
  const UNPUBLISH_ACTION = '6';

  use MediaCreationTrait;
  use StringTranslationTrait;

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
    $this->drupalLogin($admin);
  }

  /**
   * Test Restrict bulk action.
   */
  public function testRestrict() {
    $this->runTestSteps(self::RESTRICT_ACTION);
  }

  /**
   * Test Unpublish bulk action.
   */
  public function testUnpublish() {
    $this->runTestSteps(self::UNPUBLISH_ACTION);
  }

  /**
   * Test Publish bulk action.
   */
  public function testPublish() {
    $this->runTestSteps(self::PUBLISH_ACTION);
  }

  /**
   * Test Trash bulk action.
   */
  public function testTrash() {
    $this->runTestSteps(self::TRASH_ACTION);
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
    // Nothing copied the file so we do so.
    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->copy($src, $destination, TRUE);

    // Create a "Llama" media item.
    $this->createMedia([
      'title' => 'Llama',
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
    ]);
  }

  /**
   * Run through test steps.
   */
  private function runTestSteps($action) {
    $this->createMediaFile();
    $this->drupalGet('admin/ma-dash/documents');

    // Trigger search to get some results.
    $now = (new \DateTime())->format('Y-m-d H:i:s');
    $this->submitForm(['changed_op' => '<', 'changed[value]' => $now], (string) $this->t('Filter'), 'views-exposed-form-all-documents-page-1');

    $edit = [
      'action' => $action,
      'views_bulk_operations_bulk_form[0]' => TRUE,
    ];
    $this->submitForm($edit, (string) $this->t('Apply to selected items'), 'views-form-all-documents-page-1');
    $this->submitForm([], (string) $this->t('Execute action'), 'views-bulk-operations-confirm-action');
    $page = $this->getSession()->getPage();
    $page->waitFor(3, function () use ($page) {
      return $page->hasContent('Your changes have been successfully made.');
    });
  }

}
