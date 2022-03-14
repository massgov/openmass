<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures access links for unpublished content are generated properly.
 *
 * An extra check was added on `mass_hierarchy_form_alter` because
 * `$form_state->getValue('field_primary_parent')` returns a non-empty
 * array even if it doesn't have a value.
 */
class TemporaryUnpublishedAccessTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * To generate a unpbulished access link.
   */
  private function generateLink() {
    $this->getCurrentPage()->find('css', '#edit-access-unpublished-settings summary')->click();
    $links_count_before = count($this->getCurrentPage()->findAll('css', '#edit-access-unpublished-settings table tr'));
    $this->getCurrentPage()->pressButton('Get link');
    $this->getSession()->wait(1000);
    $links_count_after = count($this->getCurrentPage()->findAll('css', '#edit-access-unpublished-settings table tr'));
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
    $image = File::create([
      'uri' => 'public://test.jpg',
    ]);
    $this->markEntityForCleanup($image);

    // Create topic page.
    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test',
      'field_topic_lede' => 'Short description',
      'field_topic_bg_wide' => $image,
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
    $this->clickLink('Edit');
    $this->generateLink();

    // Ensure we don't have a parent page.
    $this->clickLink('Edit');
    $this->getCurrentPage()->fillField('Parent page', '');
    $this->getCurrentPage()->pressButton('Save');
    $this->clickLink('Edit');
    $this->generateLink();
  }

}
