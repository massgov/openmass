<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests PreventEditGovLinkConstraint on link and text fields.
 */
class PreventEditGovLinkConstraintValidationTest extends MassExistingSiteBase {

  /**
   * The error message from the constraint.
   */
  private const VALIDATION_MESSAGE = 'You must link to www.mass.gov or another public-facing website. Links to edit.mass.gov are not permitted.';

  /**
   * Ensure an edit.mass.gov link in a link field blocks save with validation.
   */
  public function testEditGovLinkInLinksDownloadsBlocksSave() {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test edit.mass.gov in link field',
      'field_application_login_links' => [
        [
          'uri' => 'https://edit.mass.gov/info-details/test-drupal-113',
        ],
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->createUser([], NULL, TRUE));

    $this->visit($node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $this->assertStringContainsString(self::VALIDATION_MESSAGE, $page_contents);
  }

  /**
   * Ensure a www.mass.gov link in a link field is allowed.
   */
  public function testWwwMassGovLinkInLinkFieldAllowsSave() {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test www.mass.gov in link field',
      'field_application_login_links' => [
        [
          'uri' => 'https://www.mass.gov/info-details/test-drupal-113',
        ],
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->createUser([], NULL, TRUE));

    $this->visit($node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $this->assertStringNotContainsString(self::VALIDATION_MESSAGE, $page_contents);
  }

  /**
   * Ensure an edit.mass.gov link in a rich text field still blocks save.
   */
  public function testEditGovLinkInTextFieldBlocksSave() {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Test edit.mass.gov in text field',
      'field_info_detail_overview' => '<p><a href="https://edit.mass.gov/info-details/test-drupal-113">Edit link</a></p>',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->createUser([], NULL, TRUE));

    $this->visit($node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->pressButton('edit-submit');
    $page_contents = $page->getContent();

    $this->assertStringContainsString(self::VALIDATION_MESSAGE, $page_contents);
  }

}

