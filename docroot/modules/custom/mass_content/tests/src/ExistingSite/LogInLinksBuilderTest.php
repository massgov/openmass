<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\mass_content\LogInLinksBuilder;
use Drupal\node\NodeInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests LoginLinksBuilder functionality.
 */
class LogInLinksBuilderTest extends MassExistingSiteBase {

  private const LINKS_1 = [
    ['title' => 'number 1', 'uri' => 'https://example.link/number-1'],
    ['title' => 'number 2', 'uri' => 'https://example.link/number-2'],
    ['title' => 'number 3', 'uri' => 'https://example.link/number-3'],
  ];

  private const LINKS_2 = [
    ['title' => 'number 4', 'uri' => 'https://example.link/number-4'],
    ['title' => 'number 5', 'uri' => 'https://example.link/number-5'],
  ];

  private const LINKS_3 = [
    ['title' => 'number 6', 'uri' => 'https://example.link/number-6'],
    ['title' => 'number 7', 'uri' => 'https://example.link/number-7'],
  ];

  private const LINKS_4 = [
    ['title' => 'number 8', 'uri' => 'https://example.link/number-8'],
  ];

  /**
   * Check links on the UI.
   */
  private function checkLinksRendered(NodeInterface $node, $links) {
    $this->drupalGet('/node/' . $node->id());
    foreach ($links as $link) {
      $href = $link['uri'];
      $this->assertSession()->elementExists('css', ".ma__utility-panel__items [href=\"$href\"]");
    }
  }

  /**
   * Checks contextual login links data.
   */
  private function checkLinksData($links, $generated_links) {
    $this->assertCount(count($links), $generated_links,
      "Different number of links found."
    );

    foreach ($generated_links as $index => $generated_link) {
      $this->assertEquals($generated_link->title, $links[$index]['title'],
        "Title is not equal at position $index");

      $this->assertEquals($generated_link->uri, $links[$index]['uri'],
        "URI is different at positoin $index");
    }
  }

  /**
   * Checks contextual logins links from LogInLinksBuilder and UI.
   */
  private function checkContextualLogInLinks($node, $links) {
    $llb = new LogInLinksBuilder();
    $generated_links = $llb->getContextualLoginLinks($node);

    $this->checkLinksData($links, $generated_links);
    $this->checkLinksRendered($node, $links);
  }

  /**
   * Creates a node with a specific parent.
   */
  private function createNodeWithParent(NodeInterface $parent, $bundle) {
    $data = [];
    $data = ['type' => $bundle];
    $data['field_primary_parent'] = $parent;
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Creates a Service node with links.
   */
  private function createServiceWithLinks($links) {
    $data = [];
    $data = ['type' => 'service_page'];
    $data['field_log_in_links'] = $links;
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Creates an Organization with links.
   */
  private function createOrganizationWithLinks($links) {
    $data = [];
    $data = ['type' => 'org_page'];
    $data['field_application_login_links'] = $links;
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Changes links on a node: service or organization.
   */
  private function changeLinks(NodeInterface $node, $links) {
    if ($node->bundle() == 'service_page') {
      $this->changeServiceLinks($node, $links);
    }
    elseif ($node->bundle() == 'org_page') {
      $this->changeOrganizationLinks($node, $links);
    }
    else {
      throw new \Exception("Bundle links change not implemented.");
    }
    $node->save();
  }

  /**
   * Changes links on a Service page.
   */
  private function changeServiceLinks($service, $links) {
    $service->field_log_in_links = $links;
  }

  /**
   * Changes links on an Organization page.
   */
  private function changeOrganizationLinks($organization, $links) {
    $organization->field_application_login_links = $links;
  }

  /**
   * Test service with own contextual login links.
   */
  public function testServiceOwnContextualLoginLinks() {
    // A few links.
    $service_1 = $this->createServiceWithLinks(self::LINKS_2);
    $this->checkContextualLogInLinks($service_1, self::LINKS_2);

    // No links.
    $service_2 = $this->createServiceWithLinks([]);
    $this->checkContextualLogInLinks($service_2, []);
  }

  /**
   * Test organization with own conextual login links.
   */
  public function testOrganizationOwnContextualLoginLinks() {
    // A few links.
    $org_1 = $this->createOrganizationWithLinks(self::LINKS_1);
    $this->checkContextualLogInLinks($org_1, self::LINKS_1);

    // No links.
    $org_1 = $this->createOrganizationWithLinks([]);
    $this->checkContextualLogInLinks($org_1, []);
  }

  /**
   * Test inheritance of contextual login links from ancestors.
   */
  public function testNodeInheritsContextualLoginLinksFromAncestors() {
    $org_1 = $this->createOrganizationWithLinks(self::LINKS_1);
    $node_1 = $this->createNodeWithParent($org_1, 'news');
    $node_2 = $this->createNodeWithParent($node_1, 'advisory');
    $node_3 = $this->createNodeWithParent($node_2, 'topic_page');
    $node_4 = $this->createNodeWithParent($node_3, 'service_page');
    $node_5 = $this->createNodeWithParent($node_4, 'service_page');
    $this->checkContextualLogInLinks($node_5, self::LINKS_1);

    // Should not inherit links due to LoginLinksBuilder::MAX_ANCESTORS.
    $node_6 = $this->createNodeWithParent($node_5, 'service_page');
    $this->checkContextualLogInLinks($node_6, []);

    // Checks when an ancestor changes.
    $this->changeLinks($org_1, self::LINKS_2);
    $this->checkContextualLogInLinks($node_5, self::LINKS_2);

    $this->changeLinks($node_4, self::LINKS_3);
    $this->checkContextualLogInLinks($node_5, self::LINKS_3);

    // // Checks when ancestor have links the service page has its own links.
    $this->changeLinks($node_5, self::LINKS_4);
    $this->checkContextualLogInLinks($node_5, self::LINKS_4);
  }

}
