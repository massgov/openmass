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
    $term = "Lorem";
    $definition = "Ipsum";

    $glossary = $this->createNode([
      'type' => 'glossary',
      'title' => 'Test Glossary',
      'field_terms' => [
        [
          'key' => $term,
          'value' => $definition,
        ],
      ],
      'moderation_state' => 'published',
    ]);

    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_service_body' => "Test definition popover " . $term,
      'moderation_state' => 'published',
    ]);

    $node->set('field_glossaries', $glossary);
    $node->save();

    $this->drupalGet($node->toUrl()->toString());
    $page = $this->getSession()->getPage();

    // The page should load with a <template id="glossary-popup-template"> and drupal settings JSON.
    $this->assertSession()->elementExists('css', '#glossary-popup-template');
    $this->assertSession()->elementExists('css', '[data-drupal-selector="drupal-settings-json"]');

    // Ensure the popup has been injected.
    $page->waitFor(10, function () use ($page) {
      $hasTemplate = $page->find('css', '#glossary-popup-template');
      return $hasTemplate !== NULL;
    });

    // Activate the popover and ensure it has the expected definition.
    $trigger = $page->find('css', '.popover__trigger');
    $dialog = $page->find('css', '.popover__dialog');

    $trigger->click();
    $this->assertTrue($dialog->isVisible());
    $this->assertSession()->elementTextContains('css', '.popover__dialog', $definition);
  }

}
