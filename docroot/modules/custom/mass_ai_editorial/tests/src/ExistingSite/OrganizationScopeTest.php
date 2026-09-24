<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_ai_editorial\ExistingSite;

use Drupal\mass_ai_editorial\OrganizationScope;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests organization subtree selection for AI editorial indexing.
 *
 * @group existing-site
 * @group mass_ai_editorial
 */
class OrganizationScopeTest extends MassExistingSiteBase {

  /**
   * A selected organization includes nested suborganizations and their content.
   */
  public function testOrganizationScopeIncludesDescendants(): void {
    $parent = $this->createOrganization('AI editorial parent');
    $child = $this->createOrganization('AI editorial child', $parent);
    $grandchild = $this->createOrganization('AI editorial grandchild', $child);
    $unrelated = $this->createOrganization('AI editorial unrelated');

    $parent_content = $this->createContent('AI editorial parent content', $parent);
    $child_content = $this->createContent('AI editorial child content', $child);
    $grandchild_content = $this->createContent('AI editorial grandchild content', $grandchild);
    $unrelated_content = $this->createContent('AI editorial unrelated content', $unrelated);

    $scope = $this->scope();
    $node_ids = $scope->loadPublishedNodeIds((int) $parent->id(), NULL, TRUE);

    foreach ([$parent, $child, $grandchild, $parent_content, $child_content, $grandchild_content] as $expected_node) {
      $this->assertContains((int) $expected_node->id(), $node_ids);
    }
    $this->assertNotContains((int) $unrelated->id(), $node_ids);
    $this->assertNotContains((int) $unrelated_content->id(), $node_ids);

    $direct_node_ids = $scope->loadPublishedNodeIds((int) $parent->id());
    $this->assertContains((int) $parent_content->id(), $direct_node_ids);
    $this->assertNotContains((int) $child_content->id(), $direct_node_ids);

    $entity_org_ids = mass_ai_editorial_entity_org_ids($grandchild_content);
    $this->assertContains((int) $parent->id(), $entity_org_ids);
    $this->assertContains((int) $child->id(), $entity_org_ids);
    $this->assertContains((int) $grandchild->id(), $entity_org_ids);
  }

  /**
   * Creates a published organization, optionally below another organization.
   */
  private function createOrganization(string $title, ?NodeInterface $parent = NULL): NodeInterface {
    $values = [
      'type' => 'org_page',
      'title' => $title . ' ' . $this->randomMachineName(8),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ];
    if ($parent) {
      $values['field_parent'] = [['target_id' => $parent->id()]];
    }

    return $this->createNode($values);
  }

  /**
   * Creates published content assigned directly to one organization.
   */
  private function createContent(string $title, NodeInterface $organization): NodeInterface {
    return $this->createNode([
      'type' => 'service_page',
      'title' => $title . ' ' . $this->randomMachineName(8),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
      'field_organizations' => [['target_id' => $organization->id()]],
    ]);
  }

  /**
   * Returns the organization scope service.
   */
  private function scope(): OrganizationScope {
    return \Drupal::service('mass_ai_editorial.organization_scope');
  }

}
