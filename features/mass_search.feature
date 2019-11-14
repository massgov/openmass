@search

Feature: Mass Search
  As a site visitor,
  I want to be able to search the site,
  so that I can find content related to my query.

  @caching
  Scenario: Verify users are redirected to search.mass.gov for results.
    Given I do not follow redirects
    And I am on "/search?q=foo%20bar"
    Then I should get a 301 HTTP response
    Then I should be redirected to "https://search.mass.gov?q=foo%20bar"
    And the page should be dynamically cacheable
    # Verify that the dynamic page cache doesn't interfere with redirection.
    When I am on "/search?q=snap"
    Then I should be redirected to "https://search.mass.gov?q=snap"

  Scenario: Verify the search form appears and points directly to search.mass.gov
    Given I am on "/"
    Then I should see an "form[action='https://search.mass.gov/']" element
    Given I am viewing any "event" node
    Then I should see an "form[action='https://search.mass.gov/']" element
