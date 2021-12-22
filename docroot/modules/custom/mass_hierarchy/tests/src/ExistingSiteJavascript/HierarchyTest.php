<?php

namespace Drupal\Tests\mass_hierarchy\ExistingSite;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests Hierachy tab.
 */
class HierarchyTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * Creates a random user with a specified role.
   */
  private function createRandomUser($role) {
    $user = User::create(['name' => $this->randomMachineName(20)]);
    $user->addRole($role);
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
   * Tests hierarchy permissions are working as expected.
   */
  public function testHierarchy() {
    $parent1Node = $this->createParentAndChildren();

    // Administrator tests.
    $this->drupalLogin($this->createRandomUser('content_team'));
    $this->drupalGet('node/' . $parent1Node->id() . '/children');
    $this->assertSession()->buttonExists('Update children');
    $this->assertSession()->elementNotExists('css', '.mass_hierarchy_cant_drag_topic_page');
    $this->assertSession()->elementNotExists('css', '.mass_hierarchy_cant_drag');

    // Editor tests.
    $this->drupalLogin($this->createRandomUser('editor'));
    $this->drupalGet('node/' . $parent1Node->id() . '/children');
    $this->assertSession()->buttonExists('Update children');
    $this->assertSession()->elementExists('css', '.mass_hierarchy_cant_drag_topic_page');
    $this->assertSession()->elementNotExists('css', '.mass_hierarchy_cant_drag');

    // Author tests.
    $this->drupalLogin($this->createRandomUser('author'));
    $this->drupalGet('node/' . $parent1Node->id() . '/children');
    $this->assertSession()->buttonNotExists('Update children');
    $this->assertSession()->elementExists('css', '.mass_hierarchy_cant_drag_topic_page');
    $this->assertSession()->elementExists('css', '.mass_hierarchy_cant_drag');
  }

}
