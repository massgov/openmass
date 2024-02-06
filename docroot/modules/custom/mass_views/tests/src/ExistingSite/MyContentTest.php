<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

class MyContentTest extends ExistingSiteBase {

  use LoginTrait;

  protected function setUp(): void {
    parent::setUp();
    // An admin is needed.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  public function testMyContent() {
    $this->drupalGet('admin/ma-dash/my-content');
    // Verify that all exposed filters are present.
    $inputs = ['edit-title', 'edit-nid', 'edit-type-1', 'edit-status-1', 'edit-node-org-filter', 'edit-nos-per-1000-cleaned', 'edit-node-label-filter', 'edit-field-collections-target-id'];
    foreach ($inputs as $input) {
      $this->assertSession()->fieldExists($input);
    }
  }

}
