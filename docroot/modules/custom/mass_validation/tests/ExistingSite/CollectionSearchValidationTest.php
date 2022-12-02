<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Class UnpublishParentConstraintTest.
 */
class CollectionSearchValidationTest extends ExistingSiteBase {
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
  protected function setUp() {
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
    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-heading-0-value', 'Test Section');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-type', 'Collection');
    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-heading-0-value', 'Test Heading');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Collection" field required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Collection" field not found.');

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $this->assertEquals($this->getSession()->getStatusCode(), 200);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');
    $page->pressButton('edit-field-organization-sections-add-more-add-more-button-org-section-long-form');
    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-heading-0-value', 'Test Section');
    $page->pressButton('edit-field-organization-sections-0-subform-field-section-long-form-content-add-more-add-more-button-collection-search');
    $page->selectFieldOption('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-type', 'External search destination (using query string)');
    $page->fillField('edit-field-organization-sections-0-subform-field-section-long-form-content-0-subform-field-search-heading-0-value', 'Test Heading');
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
    $page->fillField('edit-field-sections-0-subform-field-search-heading-0-value', 'Test Heading');
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
    $page->fillField('edit-field-sections-0-subform-field-search-heading-0-value', 'Test Heading');
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $validation_text = 'The "Search site URL" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Search site URL" field not found.');

    $validation_text = 'The "Name for query parameter" field is required.';
    $this->assertStringContainsString($validation_text, $page_contents, 'Validation message for the "Name for query parameter" field not found.');
  }

}
