<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_validation\Plugin\Validation\Constraint\PublishChildWithUnpublishedParentConstraint;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Class PublishChildWithUnpublishedParentConstraintTest.
 */
class PublishChildWithUnpublishedParentConstraintTest extends MassExistingSiteBase {

  private $user;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser();
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Assert that the constraint works properly.
   */
  public function testNodeCannotBePublishedIfItsParentIsNotPublished() {
    $node_parent_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Parent',
      'field_sub_title' => $this->randomString(20),
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::DRAFT,
    ]);

    $childNode = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::UNPUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);

    // Edit the child node, select the moderation state to published.
    $this->drupalLogin($this->user);
    $this->visit($childNode->toUrl()->toString() . '/edit');
    $this->getCurrentPage()->selectFieldOption('Change to', MassModeration::PUBLISHED);

    // Attempt to publish a child node with parent in draft.
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains(PublishChildWithUnpublishedParentConstraint::MESSAGE);

    // Attempt to publish a child node with an unpublished parent.
    $node_parent_org->moderation_state = MassModeration::UNPUBLISHED;
    $node_parent_org->save();
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains(PublishChildWithUnpublishedParentConstraint::MESSAGE);

    // Attempt to publish a child node with a trashed parent.
    $node_parent_org->moderation_state = MassModeration::TRASH;
    $node_parent_org->save();
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains(PublishChildWithUnpublishedParentConstraint::MESSAGE);

    // Attempt to publish a child node with a publish parent (should work).
    $node_parent_org->moderation_state = MassModeration::PUBLISHED;
    $node_parent_org->save();
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextNotContains(PublishChildWithUnpublishedParentConstraint::MESSAGE);
  }

}
