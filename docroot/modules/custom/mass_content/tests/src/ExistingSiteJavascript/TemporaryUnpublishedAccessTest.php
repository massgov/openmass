<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures access links for unpublished content are generated properly.
 *
 * An extra check was added on `mass_hierarchy_form_alter` because
 * `$form_state->getValue('field_primary_parent')` returns a non-empty
 * array even if it doesn't have a value.
 */
class TemporaryUnpublishedAccessTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;

  /**
   * To generate a unpublished access link.
   */
  private function generateLink(): void {
    $this->getCurrentPage()->find('css', '#edit-access-unpublished-settings summary')->click();
    $links_count_before = count($this->getCurrentPage()->findAll('css', '#edit-access-unpublished-settings table tbody tr li.copy a'));
    $table_row_count = count($this->getCurrentPage()->findAll('css', '#edit-access-unpublished-settings table tbody > tr'));
    $this->getCurrentPage()->pressButton('Get link');
    // We rely on table row count, wait for the new row to be added.
    $this->assertSession()->waitForElement('css', '#edit-access-unpublished-settings table tbody tr:nth-child(' . $table_row_count + 1 . ') li.copy a');
    $links_count_after = count($this->getCurrentPage()->findAll('css', '#edit-access-unpublished-settings table tbody tr li.copy a'));
    $this->assertEquals($links_count_before + 1, $links_count_after);
  }

  /**
   * Creates and returns a unpublished topic page node.
   */
  private function createUnpublishedTopicPage() {
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
      'moderation_state' => 'unpublished',
      'status' => 0,
    ]);

    return $node;
  }

  /**
   * Creates an admin, saves it and returns it.
   */
  private function createAdmin() {
    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    return $admin;
  }

  /**
   * Tests temporary access links work.
   */
  public function testTemporaryUnpublishedAccessGetsLink() {

    $this->drupalLogin($this->createAdmin());
    $node = $this->createUnpublishedTopicPage();
    $this->drupalGet('node/' . $node->id() . '/edit');

    // Ensure we have a parent page.
    $this->getCurrentPage()->fillField('Parent page', 'About the Massachusetts Court System');
    $this->getCurrentPage()->pressButton('Save');

    // This code used to call $this->clickLink('Edit'). However,
    // template_preprocess_menu_local_task() adds a hidden span marked as
    // visually hidden with the active tab labelled. Chrome refuses to click the
    // edit link via automation, because <span> is not supposed to be a
    // clickable element. We haven't found any core tests showing how to
    // work around this, so instead we simply re-fetch the page.
    // https://stackoverflow.com/questions/59669474/why-is-this-element-not-interactable-python-selenium
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->generateLink();

    // Ensure we don't have a parent page.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->getCurrentPage()->fillField('Parent page', '');
    $this->getCurrentPage()->pressButton('Save');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->generateLink();
  }

}
