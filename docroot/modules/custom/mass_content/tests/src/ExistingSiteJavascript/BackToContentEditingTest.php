<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures access links for unpublished content are generated properly.
 */
class BackToContentEditingTest extends ExistingSiteWebDriverTestBase {

  use LoginTrait;

  /**
   * Back to content editing on an Unpublished node works.
   *
   * An extra check was added on `mass_hierarchy_form_alter` because
   * `$form_state->getValue('field_primary_parent')` returns a non-empty
   * array even if it doesn't have a value. Creating the node programmatically
   * with `$this->createNode` will not trigger this issue.
   *
   */
  public function testBackToContentEditingOnUnpublishedPage() {
    // An admin is needed.
    $admin = User::create(['name' => $this->randomMachineName()]);
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    // Filter unpublished content.
    $this->drupalGet('admin/content');
    $this->getCurrentPage()->selectFieldOption('Publication status', 'Unpublished');
    $this->getCurrentPage()->pressButton('Apply');

    // Edit the first one.
    $this->clickLink('Edit');
    // Visit the preview.
    $this->getCurrentPage()->pressButton('Preview');
    // Back to editing.
    $this->clickLink('Back to content editing');
    // No fatals.
    $this->assertSession()->statusCodeEquals(200);
  }

}
