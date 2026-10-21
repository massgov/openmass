<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests Map field reference validation on Organization pages.
 */
class LocationReferenceValidationTest extends MassExistingSiteBase {

  private const INVALID_REFERENCE_PATTERN = '/This entity \(node: .*\) cannot be referenced\.|The referenced entity \(node: .*\) does not exist\./';

  /**
   * The user to log in and test the functionality.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser();
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Tests that non-location nodes are rejected in the Map field.
   */
  public function testOrgPageMapFieldRejectsNonLocationReference(): void {
    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => 'Test Contact for Map Validation',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $org_node = $this->createOrgPageForMapValidation();
    $page = $this->openOrgPageMapField($org_node);

    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-org-ref-locations-0-target-id', $contact->label() . ' (' . $contact->id() . ')');
    $page->pressButton('edit-submit');

    $this->assertSession()->pageTextMatches(self::INVALID_REFERENCE_PATTERN);
  }

  /**
   * Tests that valid location references are accepted in the Map field.
   */
  public function testOrgPageMapFieldAcceptsValidLocationReference(): void {
    $location = $this->createLocationWithAddress('Test Location for Map Validation');

    $org_node = $this->createOrgPageForMapValidation();
    $page = $this->openOrgPageMapField($org_node);

    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-org-ref-locations-0-target-id', $location->label() . ' (' . $location->id() . ')');
    $page->pressButton('edit-submit');

    $this->assertSession()->pageTextNotMatches(self::INVALID_REFERENCE_PATTERN);
  }

  /**
   * Creates an org page for Map field validation tests.
   */
  private function createOrgPageForMapValidation(): NodeInterface {
    return $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization for Map Validation',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Opens the org page edit form with an org_locations paragraph.
   */
  private function openOrgPageMapField(NodeInterface $org_node) {
    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-organization-sections-add-more-add-more-button-org-section-long-form');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-org-locations');

    return $page;
  }

  /**
   * Location eligible for the location_pages_with_addresses selection handler.
   */
  private function createLocationWithAddress(string $title): NodeInterface {
    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => $title . ' Contact',
      'field_display_title' => $title . ' Contact',
      'field_ref_address' => [
        Paragraph::create([
          'type' => 'address',
          'field_label' => 'Address 1',
          'field_address_address' => [
            'address_line1' => '123 Test Way',
            'locality' => 'Boston',
            'administrative_area' => 'MA',
            'postal_code' => '12345',
            'country_code' => 'US',
          ],
        ]),
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    return $this->createNode([
      'type' => 'location',
      'title' => $title,
      'field_ref_contact_info_1' => [$contact],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
