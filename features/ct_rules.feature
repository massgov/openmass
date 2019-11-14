@api
Feature: Rules Content type
  As a legal professional,
  I want page types for court rulings, standing orders, and evidence guides,
  So that I have access to the latest information about court procedure

  Scenario: Verify that the rules content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "rules" content has the correct fields

  @caching @dynamic_cache
  Scenario: Verify that the rules content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "rules" content with the title "test rule"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
