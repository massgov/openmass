<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_fields\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Verifies user approval fields are restricted to access managers.
 *
 * @group mass_fields
 */
class UserApprovalFieldAccessTest extends MassExistingSiteBase {

  /**
   * Editors must not see or edit user approval fields on their profile.
   */
  public function testEditorCannotAccessApprovalFields(): void {
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->set('field_approved', TRUE);
    $editor->set('field_approval_notes', 'Approved by access manager.');
    $editor->activate();
    $editor->save();

    foreach (['view', 'edit'] as $operation) {
      $this->assertFalse(
        $editor->get('field_approved')->access($operation, $editor),
        "An editor must not {$operation} field_approved."
      );
      $this->assertFalse(
        $editor->get('field_approval_notes')->access($operation, $editor),
        "An editor must not {$operation} field_approval_notes."
      );
    }
  }

  /**
   * Users with administer users can manage user approval fields.
   */
  public function testAdminCanAccessApprovalFields(): void {
    $user_manager = $this->createUser(['administer users']);
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->set('field_approved', TRUE);
    $editor->set('field_approval_notes', 'Approved by access manager.');
    $editor->activate();
    $editor->save();

    foreach (['view', 'edit'] as $operation) {
      $this->assertTrue(
        $editor->get('field_approved')->access($operation, $user_manager),
        "A user with administer users must be able to {$operation} field_approved."
      );
      $this->assertTrue(
        $editor->get('field_approval_notes')->access($operation, $user_manager),
        "A user with administer users must be able to {$operation} field_approval_notes."
      );
    }
  }

}
