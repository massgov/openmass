<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures access links for unpublished content are generated properly.
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
   * Tests temporary access links work.
   *
   * Properly testing this scenario is only possible creating the content
   * through UI. An extra check was added to mass_hierarchy_form_alter because
   * `$form_state->getValue('field_primary_parent')` returns a non-empty
   * array even if it doesn't have a value. Creating the node programmatically
   * with `$this->createNode` will not trigger this issue.
   */
  public function testTemporaryUnpublishedAccessGetsLink() {
    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    $this->drupalGet('admin/content');
    $this->getCurrentPage()->selectFieldOption('Content type', 'Topic Page');
    $this->getCurrentPage()->pressButton('Apply');

    // Unpublishing it.
    $this->clickLink('Edit');
    $this->getCurrentPage()->selectFieldOption('Change to', 'Unpublished');
    $this->getCurrentPage()->pressButton('Save');

    // Ensure we have a parent page.
    $this->clickLink('Edit');
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
