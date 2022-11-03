<?php

namespace Drupal\Tests\mass_seacrh\ExistingSiteJavascript;

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
    // Navigate to front page.
    $this->drupalGet("");
    $this->testSearch('banner');
  }

  /**
   * Asserts the header search provides autosuggestions.
   */
  public function testHeaderSearch() {

    // Navigate to internal page.
    $this->drupalGet("/info-details/qag-information-details2");
    $this->testSearch('header');
  }

  private function testSearch(string $identifier) {
    $search_input = sprintf("#%s-search", $identifier);
    $suggestion_element = sprintf("#%s-search-0", $identifier);
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', $search_input);

    // Get search input.
    $fields = $this->getCurrentPage()->findAll('css', $search_input);
    foreach ($fields as $field) {
      if ($field->isVisible()) {
        $field->setValue(self::SEARCH);
      }
    }

    // Give some time for the response with the results.
    $this->getSession()->wait(5000);

    $assert_session->elementExists('css', $suggestion_element);
    // Element is populated with js and means at least 1 suggestion is available.
    $suggestion = $this->getCurrentPage()->find('css', $suggestion_element);

    // Make sure the element has value.
    $this->assertNotNull($suggestion->getText());

    // Make sure the search value exists in the string.
    $this->assertStringContainsString(self::SEARCH, $suggestion->getText());
  }

}
