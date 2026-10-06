<?php

namespace Drupal\Tests\mass_hierarchy\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Term;
use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Tests Hierachy tab.
 */
class HierarchyTest extends MassExistingSiteSelenium2DriverTestBase {

  /**
   * Organization page whose Permission Group the test users belong to.
   *
   * Organization-based editing permissions are enforced (as on prod), so
   * editors can only update content of their own organization: both the
   * test content and the test users are tied to this organization.
   */
  private ?int $orgPageId = NULL;

  /**
   * The Permission Group (user_organization term) of that organization.
   */
  private ?int $permissionGroupTid = NULL;

  /**
   * Creates the organization page and its Permission Group once per test.
   */
  private function ensureOrganization(): void {
    if ($this->orgPageId) {
      return;
    }
    $term = Term::create([
      'vid' => 'user_organization',
      'name' => 'Hierarchy test PG ' . $this->randomMachineName(8),
    ]);
    $term->save();
    $this->markEntityForCleanup($term);
    $org_page = $this->createNode([
      'type' => 'org_page',
      'title' => 'Hierarchy test org ' . $this->randomMachineName(8),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    // The org page's own Permission Groups are curated by hand; set them with
    // a syncing save so no presave logic touches them.
    $org_page->set('field_content_organization', [$term->id()]);
    $org_page->setSyncing(TRUE);
    $org_page->save();
    $this->orgPageId = (int) $org_page->id();
    $this->permissionGroupTid = (int) $term->id();
  }

  /**
   * Creates a random user with a specified role.
   */
  private function createRandomUser($role) {
    $this->ensureOrganization();
    $user = $this->createUser();
    $user->addRole($role);
    $user->set('field_user_org', [$this->permissionGroupTid]);
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
    $this->ensureOrganization();
    $parent1 = [
      'type' => 'topic_page',
      'title' => 'first-parent-' . $this->randomMachineName(16),
      'status' => 1,
      'moderation_state' => 'published',
      'field_organizations' => [$this->orgPageId],
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
