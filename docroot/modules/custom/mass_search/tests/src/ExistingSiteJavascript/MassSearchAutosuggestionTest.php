<?php

namespace Drupal\Tests\mass_search\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests "Search" autosuggestions.
 */
class MassSearchAutosuggestionTest extends ExistingSiteSelenium2DriverTestBase {

  const SEARCH = 'health';

  /**
   * Asserts the banner search provides autosuggestions.
   */
  public function testHomepageBannerSearch() {
    $this->markTestSkipped('Started after the 0.341.0 release. Needs followup.');

    // Navigate to front page.
    $this->drupalGet("");
    $this->testSearch('banner');
  }

  /**
   * Asserts the header search provides autosuggestions.
   */
  public function testHeaderSearch() {
    $this->markTestSkipped('Started after the 0.341.0 release. Needs followup.');

    // Navigate to internal page.
    $this->drupalGet("/info-details/qag-information-details2");
    $this->testSearch('header');
  }

  private function testSearch(string $identifier) {
    $search_id = sprintf("#%s-search", $identifier);
    $suggestion_id = sprintf("#%s-search-0", $identifier);
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', $search_id);

    // Populate search input.
    $fields = $this->getCurrentPage()->findAll('css', $search_id);
    foreach ($fields as $field) {
      if ($field->isVisible()) {
        $field->setValue(self::SEARCH);
      }
    }
    $suggestion_element = $this->assertSession()->waitForElement('css', $suggestion_id);

    // Make sure the element has value.
    $this->assertNotNull($suggestion_element->getText());

    // Make sure the search value exists in the string.
    $this->assertStringContainsString(self::SEARCH, $suggestion_element->getText());

  }

}
