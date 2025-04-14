<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Topic Page Description visibility tests.
 */
class GlossaryPopoverTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Test that short description rendering.
   */
  public function testGlossaryPopover() {
    $hlc_reference = "Executive Office of Housing and Livable Communities (5191) - Organization";

    $glossary = $this->createNode([
      'type' => 'glossary',
      'title' => 'Test Glossary',
      'field_terms' => [
        [
          'key' => 'Lorem',
          'value' => 'Ipsum',
        ],
      ],
      'field_organizations' => $hlc_reference,
      'moderation_state' => 'published',
    ]);

    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_organizations' => $hlc_reference,
      'field_primary_parent' => $hlc_reference,
      'field_glossary' => $glossary->label() . ' (' . $glossary->id() . ') - Glossary',
      'field_service_body' => "Test definition popover Lorem",
      'moderation_state' => 'published',
    ]);

    echo("\nGlossary URL: " . $glossary->toUrl()->toString() . "\n");
    echo("\nNode URL: " . $node->toUrl()->toString() . "\n");
    $this->drupalGet($node->toUrl()->toString());
    $page = $this->getSession()->getPage();
    $page->waitFor(10, function () use ($page) {
      $hasTemplate = $page->find('css', '#glossary-popup-template');
      return $hasTemplate !== NULL;
    });
    $this->assertSession()->elementExists('css', 'main');
    // $this->assertSession()->elementExists('css', '#glossary-popup-template');
    $this->assertSession()->elementExists('css', '[data-drupal-selector="drupal-settings-json"]');

    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="drupal-settings-json"]', 'glossaries');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="drupal-settings-json"]', 'Lorem');
    $this->assertSession()->elementTextContains('css', '[data-drupal-selector="drupal-settings-json"]', 'Ipsum');
    $this->assertSession()->elementTextContains('css', 'main', 'Lorem');
  }

}
