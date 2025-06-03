<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Class UnpublishParentConstraintTest.
 */
class UnpublishedEntitiesCanBeReferencedTest extends MassExistingSiteBase {

  protected static array $uncacheableDynamicPagePatterns = [
    'admin/.*',
    '/*edit.*',
    'user/logout.*',
    'user/reset/*',
  ];

  /**
   * The user to log in and test the functionality.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser();
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Test that unpublished entities can be referenced for editor & content_team.
   */
  public function testUnpublishedEntitiesCanBeReferenced() {

    // An unpublished parent.
    // No other children.
    $parent = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Parent Organization',
      'field_sub_title' => $this->randomString(20),
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::UNPUBLISHED,
    ]);

    // A child in trash.
    $child = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization - 1' . __FUNCTION__,
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::TRASH,
      'field_primary_parent' => $parent->id(),
    ]);

    $this->drupalLogin($this->user);

    // We should be able to transition the child from trash to unpublished.
    // Check content administrators, who also have the editor role.
    $this->user->addRole('content_team');
    $this->user->addRole('editor');
    $this->user->save();
    $this->drupalGet('node/' . $child->id() . '/edit');
    $this->getCurrentPage()->selectFieldOption('Change to', MassModeration::UNPUBLISHED);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextNotMatches('/This entity \(node: .*\) cannot be referenced./');
    $this->user->removeRole('cotent_team');

    // We should be able to transition the child from trash to unpublished.
    // Check editors.
    $this->user->addRole('editor');
    $this->user->save();
    $this->drupalGet('node/' . $child->id() . '/edit');
    $this->getCurrentPage()->selectFieldOption('Change to', MassModeration::UNPUBLISHED);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextNotMatches('/This entity \(node: .*\) cannot be referenced./');
    $this->user->removeRole('editor');
  }

}
