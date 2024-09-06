<?php

namespace Drupal\Tests\mass_fields\ExistingSiteJavascript;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Test the behavior field on Topic Page.
 *
 * Ensures organization field is disabled if
 * "Disable organization(s) field and make it optional" is selected.
 */
class TopicPageAdminFieldsTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;

  /**
   * Creates and returns a topic page node.
   */
  private function createPage(): ContentEntityInterface {
    // Create required fields for topic_page.
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);

    // Create topic page.
    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test',
      'field_topic_lede' => 'Short description',
      'field_topic_content_cards' => Paragraph::create([
        'type' => 'content_card_group',
        'field_content_card_link_cards' => [
          'uri' => 'http://test.card.example.com',
          'title' => 'Test Card',
        ],
      ]),
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
      'status' => 1,
    ]);

    return $node;
  }

  /**
   * Creates an admin, saves it and returns it.
   */
  private function createAdmin(): AccountInterface {
    // An admin is needed.
    $admin = $this->createUser();
    $admin->addRole('content_team');
    $admin->activate();
    $admin->save();
    return $admin;
  }

  /**
   * Tests temporary access links work.
   */
  public function testOrgsFieldVisibilityStatesCanBeControlledByAdmins() {

    $this->drupalLogin($this->createAdmin());
    $node = $this->createPage();

    $this->drupalGet($node->toUrl('edit-form')->toString());

    // Ensure we have a parent page.
    $page = $this->getCurrentPage();
    $orgs_control_field = $page->findField('Disable organization(s) field and make it optional');
    $orgs_control_field->check();
    // Wait to make sure states are completed.
    sleep(1);
    $element = $page->find('css', '#edit-field-organizations-0-target-id');
    $this->assertTrue($element->hasAttribute('disabled'), "Organizations field must be disabled.");

    $orgs_control_field->uncheck();
    // Wait to make sure states are completed.
    sleep(1);
    $element = $page->find('css', '#edit-field-organizations-0-target-id');
    $this->assertFalse($element->hasAttribute('disabled'), "Organizations field must unlocked.");
  }

}
