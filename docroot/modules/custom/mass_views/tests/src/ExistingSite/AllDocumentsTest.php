<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

class AllDocumentsTest extends ExistingSiteBase {

  use LoginTrait;

  protected function setUp(): void {
    parent::setUp();
    // An admin is needed.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  public function testMyContent() {
    $this->drupalGet('admin/ma-dash/documents');
    // Verify that all exposed filters are present.
    $inputs = ['edit-field-title-value', 'edit-filename', 'edit-mid', 'edit-moderation-state-1', 'edit-uid', 'edit-media-org-filter', 'edit-langcode', 'edit-labels', 'edit-field-collections-target-id'];
    foreach ($inputs as $input) {
      $this->assertSession()->fieldExists($input);
    }
  }

}
