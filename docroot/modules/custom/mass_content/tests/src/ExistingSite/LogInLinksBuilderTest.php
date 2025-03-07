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

  /**
   * Check links on the UI.
   */
  private function checkNewLinksDefined(NodeInterface $node, $links) {
    $this->drupalGet('/node/' . $node->id());
    foreach ($links as $link) {
      $href = $link['uri'];
      $this->assertSession()->elementExists('css', ".ma__button-dropdown__subitems-container [href=\"$href\"]");
    }
  }

  /**
   * Checks contextual login links data.
   */
  private function checkLinksData($links, $generated_links) {
    $this->assertCount(count($links), $generated_links['links'],
      "Different number of links found."
    );

    foreach ($generated_links['links'] as $index => $generated_link) {
      $this->assertEquals($generated_link['text'], $links[$index]['title'],
        "Title is not equal at position $index");

      $this->assertEquals($generated_link['href']->toString(), $links[$index]['uri'],
        "URI is different at positoin $index");
    }
  }

  /**
   * Checks contextual logins links from LogInLinksBuilder and UI.
   */
  private function checkContextualLogInLinks($node, $links) {
    $llb = new LogInLinksBuilder();
    $generated_links = $llb->getLoginLinksWithCacheTags($node);

    $this->checkLinksData($links, $generated_links);
    $this->checkNewLinksDefined($node, $links);
  }

  /**
   * Creates a Service node with links.
   */
  private function createServiceWithLinks($links) {
    $data = ['type' => 'service_page'];
    $data['field_log_in_links'] = $links;
    $data['field_login_links_options'] = 'define_new_login_options';
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Creates an Organization with links.
   */
  private function createOrganizationWithLinks($links) {
    $data = ['type' => 'org_page'];
    $data['field_application_login_links'] = $links;
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Creates an Info Details with links.
   */
  private function createInfoDetailsWithLinks($links) {
    $data = ['type' => 'info_details'];
    $data['field_login_links_options'] = 'define_new_login_options';
    $data['field_application_login_links'] = $links;
    $data['moderation_state'] = 'published';
    return $this->createNode($data);
  }

  /**
   * Test service with own contextual login links.
   */
  public function testServiceOwnContextualLoginLinks() {
    // 1st level define new
    $service_1 = $this->createServiceWithLinks(self::LINKS_2);
    $this->checkContextualLogInLinks($service_1, self::LINKS_2);

    // 1st level disable_login_options.
    $service_1->set('field_login_links_options', 'disable_login_options');
    $service_1->save();

    $this->drupalGet('/node/' . $service_1->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");

    // 1st level inherit_parent_page_login_options.
    $service_1->set('field_login_links_options', 'inherit_parent_page_login_options');
    $service_1->save();

    // No parent set, should not be visible
    $this->drupalGet('/node/' . $service_1->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");

  }

  /**
   * Test info details with own contextual login links.
   */
  public function testInfoDetailsOwnContextualLoginLinks() {
    // 1st level define new.
    $info_details = $this->createInfoDetailsWithLinks(self::LINKS_1);
    $this->checkContextualLogInLinks($info_details, self::LINKS_1);

    // 1st level disable_login_options.
    $info_details->set('field_login_links_options', 'disable_login_options');
    $info_details->save();

    $this->drupalGet('/node/' . $info_details->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");

    // 1st level inherit_parent_page_login_options.
    $info_details->set('field_login_links_options', 'inherit_parent_page_login_options');
    $info_details->save();

    // No parent set, should not be visible
    $this->drupalGet('/node/' . $info_details->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");
  }

  /**
   * Test inheritance of contextual login links from ancestors.
   */
  public function testNodeInheritsContextualLoginLinksFromAncestors() {

    $parent_info_details = $this->createInfoDetailsWithLinks(self::LINKS_1);
    $child_service = $this->createServiceWithLinks(self::LINKS_2);

    // If set to define new option, it should render own links.
    $this->drupalGet('/node/' . $child_service->id());
    $this->checkContextualLogInLinks($child_service, self::LINKS_2);

    // Set the parent page.
    $child_service->set('field_primary_parent', $parent_info_details);
    $child_service->set('field_login_links_options', 'inherit_parent_page_login_options');
    $child_service->save();

    // If set to inherit should render parent links.
    $this->drupalGet('/node/' . $child_service->id());
    $this->checkContextualLogInLinks($child_service, self::LINKS_1);

    // If parent has disabled option, child should not render anything
    $parent_info_details->set('field_login_links_options', 'disable_login_options');
    $parent_info_details->save();

    // If set to inherit should render parent links.
    $this->drupalGet('/node/' . $child_service->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");
    $this->drupalGet('/node/' . $parent_info_details->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");

    // If set to disabled, it should not render anything.
    $child_service->set('field_login_links_options', 'disable_login_options');
    $child_service->save();

    $this->drupalGet('/node/' . $child_service->id());
    $this->assertSession()->elementNotExists('css', ".ma__button-dropdown__subitems-container");

  }

  /**
   * Test inheritance of contextual login links from ancestors.
   */
  public function testOrgPageContextualLoginLinksFromAncestors() {

    $parent_org = $this->createOrganizationWithLinks(self::LINKS_1);
    $child_service = $this->createServiceWithLinks(self::LINKS_2);

    // If set to define new option, it should render own links.
    $this->drupalGet('/node/' . $child_service->id());
    $this->checkContextualLogInLinks($child_service, self::LINKS_2);

    // Set the parent page.
    $child_service->set('field_primary_parent', $parent_org);
    $child_service->set('field_login_links_options', 'inherit_parent_page_login_options');
    $child_service->save();

    // If set to inherit should render parent links.
    $this->drupalGet('/node/' . $child_service->id());
    $this->checkContextualLogInLinks($child_service, self::LINKS_1);

  }

}
