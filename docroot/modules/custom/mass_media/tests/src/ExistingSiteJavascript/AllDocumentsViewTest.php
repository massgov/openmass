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
class AllDocumentsViewTest extends ExistingSiteSelenium2DriverTestBase {

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
   * Ensure that a document is no longer available after it is replaced.
   *
   * @see mass_media_media_update()
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaRevision() {


    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
    $this->drupalGet('admin/ma-dash/documents');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $this->getCurrentPage()->selectFieldOption('action', 'Moderation: Unpublish media');
    $this->getCurrentPage()->checkField('views_bulk_operations_bulk_form[0]');
    $this->getCurrentPage()->pressButton('Apply to selected items');
    $this->getCurrentPage()->pressButton('Execute action');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $this->assertSession()->pageTextContains('Your changes have been successfully made.');
  }


  /**
   * Ensure view content has no results if the Apply button is not clicked.
   */
  public function testView() {
    $this->drupalGet('admin/ma-dash/documents');
    $this->view = $this->page->find('css', '.view.view-all-documents');
    $view_results_selector = '.view-content .views-view-table';
    $this->assertSession()->elementNotExists('css', $view_results_selector);
    $this->getCurrentPage()->checkField('views_bulk_operations_bulk_form[0]');
    $this->getCurrentPage()->pressButton('Apply to selected items');
    $this->getCurrentPage()->pressButton('Execute action');
    $this->assertSession()->pageTextContains('Your changes have been successfully made.');
  }

}
