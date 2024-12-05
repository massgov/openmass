<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

class MyContentTest extends MassExistingSiteBase {

  protected function setUp(): void {
    parent::setUp();
    // An admin is needed.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  public function testMyContent() {
    $this->drupalGet('admin/ma-dash/my-content');
    // Verify that all exposed filters are present.
    $inputs = ['edit-title', 'edit-nid', 'edit-type-2', 'edit-status-1', 'edit-node-org-filter', 'edit-nos-per-1000-cleaned', 'edit-node-label-filter', 'edit-field-collections-target-id'];
    foreach ($inputs as $input) {
      $this->assertSession()->fieldExists($input);
    }
  }

}
