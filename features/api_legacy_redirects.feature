@api @legacy_redirects
Feature: As a MassGov alpha content editor,
  I want my legacy redirects to take effect on remote servers,
  so that I can redirect people from old urls to the new pages.

  Scenario: Prod redirects new API functions
    Given I am on "/redirects-prod.json"
    Then the response status code should be 200
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "handy_cache_tags:node:legacy_redirects" cache tag should be used

  Scenario: Staging redirects API functions
    Given I am on "/redirects-staged.json"
    Then the response status code should be 200
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "handy_cache_tags:node:legacy_redirects" cache tag should be used
