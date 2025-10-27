<?php

namespace Drupal\Tests\mass_hierarchy\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests Hierachy tab.
 */
class HierarchyTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates a random user with a specified role.
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
    $child1Node = $this->createNode($child1);

    $child2 = $child1;
    $child2['title'] = 'child2-' . $this->randomMachineName(16);
    $child2['type'] = 'how_to_page';
    $child2Node = $this->createNode($child2);

    return [$parent1Node, $child1Node, $child2Node];
  }

  /**
   * Tests hierarchy permissions are working as expected.
   */
  public function testHierarchy() {
    $parent1Node = $this->createParentAndChildren()[0];

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
