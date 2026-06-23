<?php

namespace Drupal\mass_content\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests External Organization on Colection Term pages.
 *
 * @group existing-site
 */
class ExternalOrganizationOnCollectionTermPageTest extends MassExistingSiteBase {

  /**
   * Test External Organization rendering on Collection Term pages.
   */
  public function testExternalOrganizationOnCollectionTermPage() {

    // Create one new term in collections.
    $term_name = $this->randomMachineName();
    $url = 'test_type-' . time();

    $parent_term = $this->createTerm(Vocabulary::load('collections'), [
      'name' => $term_name,
      'field_url_name' => $url,
      'field_additional_no_items_found' => 'Random empty text here ' . $term_name,
    ]);

    // Create an org
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'org from the system ' . $this->randomMachineName(),
      'field_collections' => $parent_term->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    // Create data resource whatever with that term
    $external_data_resource = $this->createNode([
      'type' => 'external_data_resource',
      'title' => $this->randomMachineName(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_collections' => $parent_term->id(),
      'field_organizations' => $org->id(),
      'field_external_organization' => '',
    ]);

    // Visit the collection term page again.
    $this->drupalGet('/collections/' . $url);
    // Notice the system's stored organization is shown on the page.
    $this->assertSession()->pageTextContains($org->label());

    // Add a value to field_external_organization, save.
    $external_org_name = 'external org ' . $this->randomMachineName();
    $external_data_resource->field_external_organization = $external_org_name;
    $external_data_resource->save();

    // Visit the collection term page again and notice
    // the external organization is showing up.
    $this->drupalGet('/collections/' . $url);
    $this->assertSession()->pageTextContains($external_org_name);

    // Change the external organization to only spaces, save.
    $external_data_resource->field_external_organization = ' ';
    $external_data_resource->save();

    // Visit the term page again and notice the external organization
    // is NOT showing up on the collection term page.
    $this->drupalGet('/collections/' . $url);
    $this->assertSession()->pageTextContains($org->label());
    $this->assertSession()->pageTextNotContains($external_org_name);

  }

}
