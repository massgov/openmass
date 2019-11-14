@api
Feature: How To Page Content type
  As a MassGov alpha content editor,
  I want to be able to add how to pages,
  so that I can inform people about how to complete tasks.

  Scenario: Verify that the how to page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "how_to_page" content has the correct fields
    And "method" paragraph has the correct fields
    And "next_step" paragraph has the correct fields

  Scenario: Verify that pathauto patterns are applied to How-To Page nodes.
    Given I am viewing a "how_to_page" content with the title "test how to page"
    Then I am on "how-to/test-how-page"

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "how_to_page" with the title "test how to page"
    Then I should see a "category" meta tag of "services"

  @caching
  Scenario: Verify that the how to content type is efficiently cacheable.
    When I am viewing a "how_to_page" content with the title "test how to item"
    Then the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used

  @caching @dynamic_cache
  Scenario: Verify that the how to content type is dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a published "how_to_page" content with the title "test how to item"
    #First we get a MISS
    Then the page should be dynamically cacheable
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
