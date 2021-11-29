<?php

namespace Drupal\Tests\mass_hierarchy\ExistingSite;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests move children action in the change_parents views.
 */
class ChangeParentViewTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * Tests move children action in the change_parents views.
   *
   * Creates a parent with 2 children.
   * Attemps to move the children to an incompatible parent.
   * Attemps to move the children to a descendant.
   * Finally moves the children to compatible parent.
   */
  public function testMoveChildren() {
    $parent1 = [
      'type' => 'topic_page',
      'title' => 'first-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
    ];
    $parent1Node = $this->createNode($parent1);

    $parent2 = [
      'type' => 'topic_page',
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

    // Create new user and login.
    $user = User::load(1)->set('status', 1);
    $user->save();
    $this->drupalLogin($user);

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
    $this->assertSession()->pageTextContains('Action processing results');

    // Reload and ensure it has no children.
    $this->getSession()->reload();
    $this->assertSession()->pageTextNotContains($child1['title']);
    $this->assertSession()->pageTextNotContains($child2['title']);

    // Check children on new parent.
    $this->drupalGet('node/' . $parent2Node->id() . '/move-children');
    $this->assertSession()->pageTextContains($child1['title']);
    $this->assertSession()->pageTextContains($child2['title']);
  }

}
