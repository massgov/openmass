<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class UnpublishParentConstraintTest.
 */
class UnpublishParentConstraintTest extends MassExistingSiteBase {

  use LoginTrait;

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
    // Content Administrators also have the Editor role.
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Assert that the validation works properly.
   */
  public function testUnpublishParentValidation() {
    $node_parent_org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Parent Organization',
      'field_sub_title' => $this->randomString(20),
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization - 1',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);

    $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization - 2',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::UNPUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);

    $this->drupalLogin($this->user);

    $this->visit($node_parent_org->toUrl()->toString() . '/move-children');
    $this->htmlOutput();

    $this->visit($node_parent_org->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'This content cannot be unpublished or trashed because it is a parent of 1 published child:';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message not found.');

    $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization 3',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);

    $this->visit($node_parent_org->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Trash');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();
    $validation_text = 'This content cannot be unpublished or trashed because it is a parent of 2 published children:';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message not found.');
  }

}
