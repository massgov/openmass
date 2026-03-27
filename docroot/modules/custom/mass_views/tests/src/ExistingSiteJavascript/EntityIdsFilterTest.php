<?php

namespace Drupal\Tests\mass_views\ExistingSiteJavascript;

use Behat\Mink\Element\DocumentElement;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests the Entity IDs filter popup on advanced search views.
 */
class EntityIdsFilterTest extends ExistingSiteSelenium2DriverTestBase {

  private DocumentElement $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->page = $this->getSession()->getPage();

    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
  }

  /**
   * Waits for a full page reload by marking the DOM before the action.
   *
   * Sets a data attribute on <body>, then polls until it disappears (meaning
   * the browser has navigated to a new page). After that, waits for
   * drupalSettings to confirm Drupal has bootstrapped the new page.
   */
  private function waitForPageReload(): void {
    // Mark the current page so we can detect when it's gone.
    $this->getSession()->evaluateScript(
      'document.body.setAttribute("data-test-marker", "old");'
    );
  }

  /**
   * After triggering an action that submits the form, wait for reload.
   */
  private function awaitNewPage(): void {
    $this->getSession()->wait(10000,
      'typeof document.body !== "undefined" && !document.body.hasAttribute("data-test-marker") && typeof drupalSettings !== "undefined"'
    );
    // Also wait for the JS behavior to re-attach (the popup button is
    // JS-generated, so its presence means Drupal.behaviors have run).
    $this->assertSession()->waitForElementVisible('css', 'button.entity-ids-open-popup');
  }

  /**
   * Tests Content IDs filter on /admin/advsearch/page.
   */
  public function testContentIdsFilterOnPageSearch() {
    // Known node IDs that exist in the test database.
    $nodeIds = [957926, 388576, 491241, 808276];

    $this->drupalGet('admin/advsearch/page');
    $session = $this->assertSession();

    // The "Content IDs" button should exist.
    $button = $this->page->find('css', 'button.entity-ids-open-popup');
    $this->assertNotNull($button, 'Content IDs button exists on the page.');
    $this->assertEqualsIgnoringCase('Content IDs', $button->getText());

    // Click the button to open the popup.
    $button->click();
    $session->waitForElementVisible('css', '.entity-ids-popup-overlay');
    $overlay = $this->page->find('css', '.entity-ids-popup-overlay');
    $this->assertNotNull($overlay, 'Popup overlay is visible.');

    // The textarea should be present inside the popup.
    $textarea = $overlay->find('css', '.entity-ids-popup-textarea');
    $this->assertNotNull($textarea, 'Textarea is present in the popup.');

    // Enter node IDs (newline-separated).
    $textarea->setValue(implode("\n", $nodeIds));

    // Click Apply inside the popup - this triggers form.submit().
    $applyBtn = $overlay->find('css', '.entity-ids-popup-apply');
    $this->assertNotNull($applyBtn, 'Apply button is present in the popup.');
    $this->waitForPageReload();
    $applyBtn->click();
    $this->awaitNewPage();

    // Verify tags are rendered for all IDs (re-query from fresh DOM).
    $tags = $this->page->findAll('css', '.entity-ids-tags .entity-ids-tag');
    $this->assertCount(4, $tags, 'Four ID tags are displayed after applying the filter.');

    // Verify the entity_ids query parameter is present in the URL.
    $this->assertStringContainsString('entity_ids=', $this->getSession()->getCurrentUrl());

    // Verify the view shows results (the table should exist).
    $session->elementExists('css', '.view-content');

    // Verify the filtered results contain the expected node titles.
    $expectedTitles = [
      'QAG test form with Gravity input parameters',
      '_QAG Information Details_2',
      'QAG_Campaign landing with solid color key message header',
      'QAG Info Details Table samples',
    ];
    $resultsText = $this->page->find('css', '.view-content')->getText();
    foreach ($expectedTitles as $title) {
      $this->assertStringContainsString($title, $resultsText, "Result contains node title: $title");
    }

    // Remove one tag - this triggers form.submit().
    $removeBtn = $this->page->find('css', '.entity-ids-tag-remove');
    $this->assertNotNull($removeBtn, 'Remove button exists on a tag.');
    $this->waitForPageReload();
    $removeBtn->click();
    $this->awaitNewPage();

    // Verify only three tags remain (re-query from fresh DOM).
    $tags = $this->page->findAll('css', '.entity-ids-tags .entity-ids-tag');
    $this->assertCount(3, $tags, 'Three ID tags remain after removing one.');

    // Clear all tags - this triggers form.submit().
    $clearAllBtn = $this->page->find('css', '.entity-ids-clear-all');
    $this->assertNotNull($clearAllBtn, 'Clear all button exists.');
    $this->waitForPageReload();
    $clearAllBtn->click();
    $this->awaitNewPage();

    // Verify no tags remain (re-query from fresh DOM).
    $tags = $this->page->findAll('css', '.entity-ids-tags .entity-ids-tag');
    $this->assertCount(0, $tags, 'No tags remain after clearing all.');
  }

  /**
   * Tests Document IDs filter on /admin/ma-dash/documents-advanced-search.
   */
  public function testDocumentIdsFilterOnDocumentsSearch() {
    // Known document (media) IDs that exist in the test database.
    $documentIds = [2671746, 2671726, 1885626, 2653761];

    $this->drupalGet('admin/ma-dash/documents-advanced-search');
    $session = $this->assertSession();

    // The "Document IDs" button should exist.
    $button = $this->page->find('css', 'button.entity-ids-open-popup');
    $this->assertNotNull($button, 'Document IDs button exists on the page.');
    $this->assertEqualsIgnoringCase('Document IDs', $button->getText());

    // Click the button to open the popup.
    $button->click();
    $session->waitForElementVisible('css', '.entity-ids-popup-overlay');
    $overlay = $this->page->find('css', '.entity-ids-popup-overlay');
    $this->assertNotNull($overlay, 'Popup overlay is visible.');

    // The textarea should be present.
    $textarea = $overlay->find('css', '.entity-ids-popup-textarea');
    $this->assertNotNull($textarea, 'Textarea is present in the popup.');

    // Enter document IDs (comma-separated to test that format).
    $textarea->setValue(implode(', ', $documentIds));

    // Click Apply - this triggers form.submit().
    $applyBtn = $overlay->find('css', '.entity-ids-popup-apply');
    $this->waitForPageReload();
    $applyBtn->click();
    $this->awaitNewPage();

    // Verify four tags are rendered (re-query from fresh DOM).
    $tags = $this->page->findAll('css', '.entity-ids-tags .entity-ids-tag');
    $this->assertCount(4, $tags, 'Four ID tags are displayed after applying the filter.');

    // Verify URL contains the filter parameter.
    $this->assertStringContainsString('entity_ids=', $this->getSession()->getCurrentUrl());

    // Verify the view shows results.
    $session->elementExists('css', '.view-content');

    // Verify the filtered results contain the expected document titles.
    $expectedTitles = [
      'qag test doc - Chinese Simplified',
      'qag test doc - Portuguese, Brazil',
      '_QAG Document_pdf',
      'qag Test spanish version of document',
    ];
    $resultsText = $this->page->find('css', '.view-content')->getText();
    foreach ($expectedTitles as $title) {
      $this->assertStringContainsString($title, $resultsText, "Result contains document title: $title");
    }

    // Test cancel button closes popup without applying.
    $button = $this->page->find('css', 'button.entity-ids-open-popup');
    $button->click();
    $session->waitForElementVisible('css', '.entity-ids-popup-overlay');
    $overlay = $this->page->find('css', '.entity-ids-popup-overlay');
    $cancelBtn = $overlay->find('css', '.entity-ids-popup-cancel');
    $this->assertNotNull($cancelBtn, 'Cancel button is present in the popup.');
    $cancelBtn->click();

    // Popup should be hidden after cancel.
    $this->assertFalse($overlay->isVisible(), 'Popup is hidden after clicking Cancel.');

    // Tags should still show the previously applied IDs (not changed by cancel).
    $tags = $this->page->findAll('css', '.entity-ids-tags .entity-ids-tag');
    $this->assertCount(4, $tags, 'Tags remain unchanged after cancelling the popup.');
  }

}
