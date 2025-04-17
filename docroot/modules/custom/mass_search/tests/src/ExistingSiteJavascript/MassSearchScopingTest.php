<?php

namespace Drupal\Tests\mass_search\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests "Search" scoping.
 */
class MassSearchScopingTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests the default search experience with no additional scoping.
   */
  public function testDefaultSearch() {
    $massgov_page = '/qag-service1';
    $query = 'searchterm';
    $this->drupalGet($massgov_page);

    // Check how many suggestions there are.
    $search_field = $this->setSearchInput($query);
    $search_field->focus();
    $suggestions_length = count($this->getSearchSuggestions($query));

    // Perform search
    $form = $search_field;
    while ($form && $form->getTagName() !== 'form') {
      $form = $form->getParent();
    }
    $form->submit();
    $this->assertSession();

    // Assert search results are scoped to microsite.
    $search_url = parse_url($this->getUrl());
    $this->assertIsString('search.mass.gov', $search_url['host']);
    $this->assertEquals($search_url['query'], "q=$query");

    // Check each suggestion.
    for ($i = 0; $i < $suggestions_length; $i++) {
      $this->drupalGet($massgov_page);
      $suggestion = $this->getSearchSuggestions($query)[$i];
      $this->testSearchSuggestion($suggestion, $query);
    }
  }

  /**
   * Tests the search experience when scoped within a microsite.
   */
  public function testMicrositeSearch() {
    $massgov_page = '/qag-servicemicrosite';
    $query = 'searchterm';
    $this->drupalGet($massgov_page);

    // Get microsite's scoping value from metatag
    $meta = $this->getCurrentPage()->find('css', 'meta[name="mg_microsite"]');
    $this->assertNotNull($meta);
    $microsite = $meta->getAttribute('content');

    // Check how many suggestions there are.
    $search_field = $this->setSearchInput($query);
    $suggestions_length = count($this->getSearchSuggestions($query));

    // Perform search
    $form = $search_field;
    while ($form && $form->getTagName() !== 'form') {
      $form = $form->getParent();
    }
    $form->submit();

    // Assert search results are scoped to microsite.
    $search_url = parse_url($this->getUrl());
    $this->assertIsString('search.mass.gov', $search_url['host']);
    $this->assertStringContainsString("microsite=$microsite", $search_url['query']);

    // Check each suggestion.
    for ($i = 0; $i < $suggestions_length; $i++) {
      $this->drupalGet($massgov_page);
      $suggestion = $this->getSearchSuggestions($query)[$i];
      $type = $suggestion->getAttribute('data-type');
      $this->testSearchSuggestion($suggestion, $query);

      if ($type !== 'microsite') {
        $this->assertStringNotContainsString("microsite=", parse_url($this->getUrl())['query']);
      }
    }
  }

  /**
   * Sets the search input field and returns the search field element.
   *
   * @param string $query
   *   The search query.
   *
   * @return \Behat\Mink\Node\ElementInterface
   *   The search field element.
   */
  private function setSearchInput($query) {
    $assert_session = $this->assertSession();
    $page = $this->getCurrentPage();
    $assert_session->elementExists('css', 'input#header-search');
    $searchField = $page->find('css', 'input#header-search');
    $searchField->setValue($query);
    return $searchField;
  }

  /**
   * Returns the search suggestions.
   *
   * @param string $query
   *   The search query.
   *
   * @return \Behat\Mink\Node\ElementInterface[]
   *   The search suggestions.
   */
  private function getSearchSuggestions($query) {
    $searchField = $this->setSearchInput($query);
    $searchField->focus();
    $suggestions = $this->getCurrentPage()->findAll('css', '#search-suggestions .ma__header-search-suggestion-option');
    foreach ($suggestions as $key => $suggestion) {
      if (!$suggestion->isVisible()) {
        unset($suggestions[$key]);
      }
    }

    return array_values($suggestions);
  }

  /**
   * Tests a single search suggestion.
   *
   * @param \Behat\Mink\Node\ElementInterface $suggestion
   *   The search suggestion.
   * @param string $query
   *   The search query.
   */
  private function testSearchSuggestion($suggestion, $query) {
    $type = $suggestion->getAttribute('data-type');
    $value = $suggestion->getAttribute('data-value');
    $input = $this->setSearchInput($query);
    $input->focus();
    $suggestion->click();
    $this->assertSession();
    $search_url = parse_url($this->getUrl());
    $this->assertIsString('search.mass.gov', $search_url['host']);
    if ($type && $value) {
      $this->assertStringContainsString("$type=$value", $search_url['query']);
    }
  }

}
