@api
Feature: News Content type
  As a MassGov alpha content editor,
  I want to be able to add news pages,
  so that I can inform people about news and press releases.

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "news" content with the title "test service page"
    Then I should see a "category" meta tag of "news"

  @caching @dynamic_cache
  Scenario: Verify that the news content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "news" content with the title "test news item"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached


