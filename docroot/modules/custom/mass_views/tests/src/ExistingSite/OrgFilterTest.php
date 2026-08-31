<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests the node Organization Views filter.
 *
 * @group existing-site
 */
class OrgFilterTest extends MassExistingSiteBase {

  /**
   * Two Organization filters in one display both constrain the result set.
   *
   * parent_reports page_1 has Organization of Child and Organization of Parent.
   * A hard-coded join alias dropped the second filter's subquery.
   */
  public function testTwoOrganizationFiltersApplyIntersection(): void {
    $prefix = 'DP-48112-twofilter-' . $this->randomMachineName(8);
    $child_org = $this->createOrgPage($prefix . ' child org');
    $parent_org = $this->createOrgPage($prefix . ' parent org');
    $decoy_org = $this->createOrgPage($prefix . ' decoy org');

    $parent_node = $this->createServicePage($prefix . ' parent node', $parent_org);
    $decoy_parent = $this->createServicePage($prefix . ' decoy parent', $decoy_org);

    $matching_child = $this->createServicePage($prefix . ' matching child', $child_org, $parent_node);
    $child_only_decoy = $this->createServicePage($prefix . ' child-only decoy', $child_org, $decoy_parent);
    $parent_only_decoy = $this->createServicePage($prefix . ' parent-only decoy', $parent_org, $parent_node);

    $view = $this->parentReportsView($prefix);
    $view->filter['node_org_filter']->options['exposed'] = FALSE;
    $view->filter['node_org_filter']->operator = '=';
    $view->filter['node_org_filter']->value = [['target_id' => $child_org->id()]];
    $view->filter['node_org_filter_1']->options['exposed'] = FALSE;
    $view->filter['node_org_filter_1']->operator = '=';
    $view->filter['node_org_filter_1']->value = [['target_id' => $parent_org->id()]];
    $view->execute();

    $nids = $this->resultNids($view);
    $this->assertContains(
      (int) $matching_child->id(),
      $nids,
      'Matching child must appear when both organization filters apply.'
    );
    $this->assertNotContains(
      (int) $child_only_decoy->id(),
      $nids,
      'Child-only decoy must not appear; that is the silent single-filter regression.'
    );
    $this->assertNotContains(
      (int) $parent_only_decoy->id(),
      $nids,
      'Parent-only decoy must not appear.'
    );
  }

  /**
   * Loads parent_reports page_1 constrained to a unique title prefix.
   */
  private function parentReportsView(string $title_prefix): object {
    $view = Views::getView('parent_reports');
    $this->assertNotNull($view);
    $view->setDisplay('page_1');
    $view->setItemsPerPage(0);
    $view->initHandlers();
    $this->assertArrayHasKey('node_org_filter', $view->filter);
    $this->assertArrayHasKey('node_org_filter_1', $view->filter);
    $view->filter['title']->options['exposed'] = FALSE;
    $view->filter['title']->operator = 'contains';
    $view->filter['title']->value = $title_prefix;
    return $view;
  }

  /**
   * Creates a published org_page.
   */
  private function createOrgPage(string $title): NodeInterface {
    return $this->createNode([
      'type' => 'org_page',
      'title' => $title,
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates a published service_page, optionally tagged and parented.
   */
  private function createServicePage(string $title, ?NodeInterface $org = NULL, ?NodeInterface $parent = NULL): NodeInterface {
    $values = [
      'type' => 'service_page',
      'title' => $title,
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ];
    if ($org) {
      $values['field_organizations'] = [['target_id' => $org->id()]];
    }
    if ($parent) {
      $values['field_primary_parent'] = [['target_id' => $parent->id()]];
    }
    return $this->createNode($values);
  }

  /**
   * Node ids present in a Views result set.
   *
   * @return int[]
   *   The nids.
   */
  private function resultNids(object $view): array {
    $nids = [];
    foreach ($view->result as $row) {
      foreach (get_object_vars($row) as $key => $value) {
        if ($key === 'nid' || str_ends_with((string) $key, '_nid')) {
          $nids[] = (int) $value;
          break;
        }
      }
    }
    return $nids;
  }

}
