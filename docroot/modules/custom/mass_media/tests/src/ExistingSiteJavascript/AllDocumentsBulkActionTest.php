<?php

namespace Drupal\Tests\mass_media\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests "All Content" view requires input to show content to speed up login.
 */
class AllDocumentsBulkActionTest extends ExistingSiteSelenium2DriverTestBase {
  const RESTRICT_ACTION = '2';
  const UNPUBLISH_ACTION = '3';
  const TRASH_ACTION = '4';
  const PUBLISH_ACTION = '5';

  use LoginTrait;
  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    /** @var \Drupal\Tests\DocumentElement */
    $this->page = $this->getSession()->getPage();

    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
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
    $this->getCurrentPage()->find('css', 'select[name="action"]')->selectOption($action);
    $this->getCurrentPage()->find('css', 'input[name="views_bulk_operations_bulk_form[0]"]')->check();
    $this->getCurrentPage()->pressButton('Apply to selected items');
    $this->getCurrentPage()->pressButton('Execute action');
    $this->assertSession()->waitForText('Your changes have been successfully made.');
  }

}
