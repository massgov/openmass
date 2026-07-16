<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests validation for the Map field on Organization pages.
 */
class LocationReferenceValidationTest extends MassExistingSiteBase {

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
   * Assert that non-location nodes cannot be saved in the Map field.
   */
  public function testOrgPageMapFieldRejectsNonLocationReference(): void {
    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => 'Test Contact for Map Validation',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization for Map Validation',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-organization-sections-add-more-add-more-button-org-section-long-form');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-org-locations');
    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-org-ref-locations-0-target-id', $contact->label() . ' (' . $contact->id() . ')');
    $page->pressButton('edit-submit');

    $validation_text = 'Only location pages can be referenced in this field.';
    $this->assertStringContainsString($validation_text, $page->getContent(), 'Validation message for the Map field not found.');
  }

}
