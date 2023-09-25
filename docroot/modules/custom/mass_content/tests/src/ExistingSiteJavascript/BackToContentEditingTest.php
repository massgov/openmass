<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures access links for unpublished content are generated properly.
 */
class BackToContentEditingTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;
  use ParagraphsTestBaseTrait;

  /**
   * Creates and returns a unpublished topic page node.
   */
  private function createUnpublishedTopicPage() {
    // Create required fields for topic_page.
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $this->markEntityForCleanup($image);

    // Create topic page.
    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test',
      'field_topic_lede' => 'Short description',
      'field_organizations' => [$org_node],
      'moderation_state' => 'unpublished',
      'field_topic_content_cards' => [
        Paragraph::create([
          'type' => 'content_card_group',
          'field_content_card_link_cards' => [
            'uri' => 'http://test.card',
            'title' => 'Test Card',
          ],
        ]),
      ],
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
   * Back to content editing on an Unpublished node works.
   *
   * An extra check was added on `mass_hierarchy_form_alter` because
   * `$form_state->getValue('field_primary_parent')` returns a non-empty
   * array even if it doesn't have a value.
   */
  public function testBackToContentEditingOnUnpublishedPage() {
    $this->drupalLogin($this->createAdmin());
    $node = $this->createUnpublishedTopicPage();

    // Edit the node.
    $this->drupalGet('node/' . $node->id() . '/edit');
    // Visit the preview.
    $this->getCurrentPage()->pressButton('Preview');
    // Back to editing.
    $this->clickLink('Back to content editing');
    $title = $this->getSession()->getPage()->find('css', 'h1')->getText();
    $this->assertEquals($title, 'EDIT TOPIC PAGE Test');
  }

}
