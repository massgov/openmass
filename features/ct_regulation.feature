@api
Feature: Regulation Page Content type
  As a MassGov alpha content editor,
  I want to be able to add regulation pages,
  so that I can inform people about regulations and enable them to take action for it.

  Scenario: Verify that the regulation content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "regulation" content has the correct fields

  Scenario: Verify that pathauto patterns are applied to regulation node.
    Given I am viewing an "regulation" content with the title "Test Regulation"
    Then I am on "regulations/test-regulation"

  @caching @dynamic_cache
  Scenario: Verify that the regulation content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "regulation" content with the title "test regulation"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
