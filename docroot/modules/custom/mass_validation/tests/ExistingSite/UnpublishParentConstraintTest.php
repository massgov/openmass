<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class UnpublishParentConstraintTest.
 */
class UnpublishParentConstraintTest extends ExistingSiteBase {

  use LoginTrait;

  private $user;

  /**
   * Create the user.
   */
  protected function setUp() {
    parent::setUp();
    $user = User::create(['name' => $this->randomMachineName()]);
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
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node_child_org_1 = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization 1',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);

    $this->drupalLogin($this->user);
    $this->visit($node_parent_org->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();
    $validation_text = 'This content cannot be unpublished or trashed because it is a parent of 1 child:';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message not found.');
    $node_child_org_2 = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization 2',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_primary_parent' => $node_parent_org->id(),
    ]);
    $this->visit($node_parent_org->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Trash');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();
    $validation_text = 'This content cannot be unpublished or trashed because it is a parent of 2 children:';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message not found.');
  }

}
