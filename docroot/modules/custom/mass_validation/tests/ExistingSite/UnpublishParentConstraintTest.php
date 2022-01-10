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
    $user->addRole('content_team');
    // $user->addRole('administrator');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Assert that the validation works properly.
   */
  public function no_testUnpublishParentValidation() {
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

  public function testSomething() {

    $parent = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Parent Organization',
      'field_sub_title' => $this->randomString(20),
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::UNPUBLISHED,
    ]);

    $child = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Child Organization - 1' . __FUNCTION__,
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::TRASH,
      'field_primary_parent' => $parent->id(),
    ]);


    // If I trash a child,

    // then unpublish the parent


    // $parent->moderation_state = MassModeration::UNPUBLISHED;
    // $parent->save();



    // which has no other children,

    // I cannot transition the trashed child to unpublished because
    // it knows that the parent is unpublished.


    // $this->htmlOutput('AHORA!');

    $this->htmlOutput();
    $this->drupalGet('user/'. $this->user->id() . '/edit');
    $this->htmlOutput();

    $this->drupalLogin($this->user);
    $this->drupalGet('node/' . $child->id() . '/edit');
    $this->getCurrentPage()->selectFieldOption('Change to', MassModeration::UNPUBLISHED);
    $this->getCurrentPage()->pressButton('Save');

    $this->htmlOutput();

    // $this->user->addRole('administrator');
    // $this->user->save();
    // $this->drupalGet('node/' . $child->id() . '/edit');
    // $this->getCurrentPage()->selectFieldOption('Change to', MassModeration::UNPUBLISHED);
    // $this->getCurrentPage()->pressButton('Save');
    // $this->htmlOutput();


    // $this->assertSession()->pageTextNotMatches('/This entity \(node: .*\) cannot be referenced./');

    // die();


  }

}

