<?php

namespace Drupal\Tests\mass_hierarchy\ExistingSiteJavascript;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests move children action in the change_parents views.
 */
class ChangeParentViewTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Loads the admin and logs in.
   */
  private function adminLogin() {
    $user = User::load(1)->set('status', 1);
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Creates a random user.
   */
  private function createRandomUser($role) {
    $user = $this->createUser();
    $user->addRole($role);
    // Also add editor role for testing Content Administrator permissions.
    if ($role == 'content_team') {
      $user->addRole('editor');
    }
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Creates parent and children to be able to test.
   */
  private function createParentAndChildren() {
    $parent1 = [
      'type' => 'topic_page',
      'title' => 'first-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent1Node = $this->createNode($parent1);

    $child1 = [
      'title' => 'child1-' . $this->randomMachineName(16),
      'field_primary_parent' => $parent1Node->id(),
    ] + $parent1;
    $this->createNode($child1);

    $child2 = $child1;
    $child2['title'] = 'child2-' . $this->randomMachineName(16);
    $child2['type'] = 'how_to_page';
    $this->createNode($child2);

    return $parent1Node;
  }

  /**
   * Tests access to change parent action on move-children, and topic pages.
   */
  public function testAccessFromDifferentRoles() {
    $parent1Node = $this->createParentAndChildren();

    // Administrator tests.
    $this->drupalLogin($this->createRandomUser('content_team'));
    $this->drupalGet('node/' . $parent1Node->id() . '/move-children');
    $this->assertSession()->buttonExists('Change parent');
    $this->assertSession()->pageTextContains('Topic Page Published');

    // Editor tests.
    $this->drupalLogin($this->createRandomUser('editor'));
    $this->drupalGet('node/' . $parent1Node->id() . '/move-children');
    $this->assertSession()->buttonExists('Change parent');
    $this->assertSession()->pageTextNotContains('Topic Page Published');

    // Author tests.
    $this->drupalLogin($this->createRandomUser('author'));
    $this->drupalGet('node/' . $parent1Node->id() . '/move-children');
    $this->assertSession()->buttonNotExists('Change parent');
    $this->assertSession()->pageTextNotContains('Topic Page Published');
  }

  /**
   * Tests move children action in the change_parents views.
   *
   * Creates a parent with 2 children.
   * Attemps to move the children to an incompatible parent.
   * Attemps to move the children to a descendant.
   * Finally moves the children to compatible parent.
   */
  public function testMoveChildren() {
    $this->adminLogin();

    $parent1 = [
      'type' => 'service_page',
      'title' => 'first-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent1Node = $this->createNode($parent1);

    $parent2 = [
      'type' => 'service_page',
      'title' => 'second-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent2Node = $this->createNode($parent2);

    $parent3 = [
      'type' => 'page',
      'title' => 'wrong-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent3Node = $this->createNode($parent3);

    $child1 = [
      'title' => 'child1-' . $this->randomMachineName(16),
      'field_primary_parent' => $parent1Node->id(),
    ] + $parent1;

    $child1Node = $this->createNode($child1);

    $childOfChild1 = [
      'title' => 'child-of-child1-' . $this->randomMachineName(16),
      'field_primary_parent' => $child1Node->id(),
    ] + $parent1;

    $childOfChild1Node = $this->createNode($childOfChild1);

    $child2 = [
      'title' => 'child2-' . $this->randomMachineName(16),
      'field_primary_parent' => $parent1Node->id(),
    ] + $parent1;

    $this->createNode($child2);

    // Visit move children tab.
    $this->drupalGet('node/' . $parent1Node->id() . '/move-children');

    // Checking the page has the 2 children created above.
    $this->assertSession()->pageTextContains($child1['title']);
    $this->assertSession()->pageTextContains($child2['title']);

    // Select all of them to change their parent.
    $this->getCurrentPage()->find('css', '.vbo-table .select-all input:nth-child(1)')->check();
    $this->getCurrentPage()->pressButton('Change parent');

    // Select a wrong parent (incompatible bundle).
    $this->getCurrentPage()->fillField('New parent', $parent3Node->label());
    $this->getCurrentPage()->pressButton('Change parent');
    $this->assertSession()->pageTextContains('1 error has been found');

    // Select a wrong parent (descendant).
    $this->getCurrentPage()->fillField('New parent', $childOfChild1Node->label());
    $this->getCurrentPage()->pressButton('Change parent');
    $this->assertSession()->pageTextContains('The new parent is a descendant of');

    // Select a correct parent.
    $this->getCurrentPage()->fillField('New parent', $parent2Node->label());

    // Wait for the bulk operations to happen.
    $this->getCurrentPage()->pressButton('Change parent');
    $this->getSession()->wait(3000);
    $this->htmlOutput();

    // Check done message.
    $this->assertSession()->pageTextContains('Performing Change parent on selected entities.');

    // Reload and ensure it has no children.
    $this->getSession()->reload();
    $this->assertSession()->pageTextNotContains($child1['title']);
    $this->assertSession()->pageTextNotContains($child2['title']);

    // Check children on new parent.
    $this->drupalGet('node/' . $parent2Node->id() . '/move-children');
    $this->assertSession()->pageTextContains($child1['title']);
    $this->assertSession()->pageTextContains($child2['title']);
  }

  /**
   * Tests parent bulk update when the current revision is not the latest.
   */
  public function testMoveChildrenWhenItsDraft() {
    $this->adminLogin();

    // Create a node to be the first parent of $child1.
    $parent1 = [
      'type' => 'service_page',
      'title' => 'first-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent1Node = $this->createNode($parent1);

    // Create a child for parent1.
    $child1 = [
      'title' => 'child1-' . $this->randomMachineName(16),
      'field_primary_parent' => $parent1Node->id(),
    ] + $parent1;

    /** @var \Drupal\node\Entity\Node $child1Node */
    $child1Node = $this->createNode($child1);

    // Create draft revision of the child.
    $child1Node->moderation_state = 'draft';
    $child1Node->status = 0;
    $child1Node->save();

    // Create a second parent node.
    $parent2 = [
      'type' => 'service_page',
      'title' => 'second-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent2Node = $this->createNode($parent2);

    $randomUser = $this->createRandomUser('administrator');
    $this->drupalLogin($randomUser);

    // Selecting the node to change the parent.
    $this->drupalGet('node/' . $parent1Node->id() . '/move-children');
    $this->assertSession()->pageTextContains($child1['title']);
    $this->getCurrentPage()->find('css', '.vbo-table .select-all input:nth-child(1)')->check();
    $this->getCurrentPage()->pressButton('Change parent');

    // Bulk updating to the new parent (second parent).
    $this->getCurrentPage()->fillField('New parent', $parent2Node->label());
    $this->getCurrentPage()->pressButton('Change parent');
    $this->getSession()->wait(2000);

    /** @var \Drupal\Node\NodeStorage */
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $latest_vid = $node_storage->getLatestRevisionId($child1Node->id());

    // Check revision author is the current user.
    $this->drupalGet('node/' . $child1Node->id() . '/revisions');
    $this->assertSession()->pageTextContains('by ' . $randomUser->getAccountName());
    // Check we have a custom message for this action on the revision log.
    $this->assertSession()->pageTextContains('Revision created with "Move Children" feature. (Draft)');
    $this->assertSession()->pageTextContains('Revision created with "Move Children" feature. (Published)');
    $this->htmlOutput();

    // Make the latest draft the current revision.
    $this->drupalGet('node/' . $child1Node->id() . '/revisions/' . $latest_vid . '/revert');
    $this->htmlOutput();
    $this->getCurrentPage()->pressButton('Revert');

    // Edit the node.
    // This code used to call $this->clickLink('Edit'). However,
    // template_preprocess_menu_local_task() adds a hidden span marked as
    // visually hidden with the active tab labelled. Chrome refuses to click the
    // edit link via automation, because <span> is not supposed to be a
    // clickable element. We haven't found any core tests showing how to
    // work around this, so instead we simply re-fetch the page.
    // https://stackoverflow.com/questions/59669474/why-is-this-element-not-interactable-python-selenium
    $this->drupalGet('node/' . $child1Node->id() . '/edit');

    // Check it has the second parent.
    $this->assertSession()->fieldValueEquals('Parent page', $parent2Node->label() . ' (' . $parent2Node->id() . ')');
  }

}
