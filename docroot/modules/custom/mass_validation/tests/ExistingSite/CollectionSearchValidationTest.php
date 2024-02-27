<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class UnpublishParentConstraintTest.
 */
class CollectionSearchValidationTest extends MassExistingSiteBase {
  use LoginTrait;

  /**
   * The user to log in and test the functionality.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();
    $user = User::create(['name' => $this->randomMachineName()]);
    // Content Administrators also have the Editor role.
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Assert that the Custom Search paragraph validation works properly on organization nodes.
   */
  public function testOrgCollectionSearchValidation() {

    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Organization ',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-organization-sections-add-more-add-more-button-org-section-long-form');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-type', 'Collection');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Collection" field required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Collection" field not found.');

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-organization-sections-add-more-add-more-button-org-section-long-form');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-type', 'External search destination (using query string)');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Search site URL" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Search site URL" field not found.');

    $validation_text = 'The "Name for query parameter" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Name for query parameter" field not found.');
  }

  /**
   * Assert that the Custom Search paragraph validation works properly on promotional page nodes.
   */
  public function testPromoPageCollectionSearchValidation() {

    $org_node = $this->createNode([
      'type' => 'campaign_landing',
      'title' => 'Test Promo Page ',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('field-sections-collection-search-add-more');
    $page->selectFieldOption('edit-field-sections-0-subform-field-search-type', 'Collection');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Collection" field required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Collection" field not found.');

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('field-sections-collection-search-add-more');
    $page->selectFieldOption('edit-field-sections-0-subform-field-search-type', 'External search destination (using query string)');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Search site URL" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Search site URL" field not found.');

    $validation_text = 'The "Name for query parameter" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Name for query parameter" field not found.');
  }

  /**
   * Assert that the Custom Search paragraph validation works properly on service page nodes.
   */
  public function testServicePageCollectionSearchValidation() {

    $org_node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page ',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-service-sections-add-more-add-more-button-service-section');
    $page->pressButton('edit-field-service-sections-0-subform-field-service-section-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-service-sections-0-subform-field-service-section-content-0-subform-field-search-type', 'Collection');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Collection" field required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Collection" field not found.');

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-service-sections-add-more-add-more-button-service-section');
    $page->pressButton('edit-field-service-sections-0-subform-field-service-section-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-service-sections-0-subform-field-service-section-content-0-subform-field-search-type', 'External search destination (using query string)');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Search site URL" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Search site URL" field not found.');

    $validation_text = 'The "Name for query parameter" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Name for query parameter" field not found.');
  }

}
