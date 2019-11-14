@api
Feature: Location Details Page Content type
  As a MassGov alpha content editor,
  I want to be able to add location detail pages,
  so that I can inform people about additional information regarding locations.

  Scenario: Verify that the service details content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "location_details" content has the correct fields
    And "section" paragraph has the correct fields

  @caching @dynamic_cache
  Scenario: Verify that the location details content type is efficiently and dynamically cacheable.
    Given I am logged in as a user with the "editor" role
    When I am viewing a "location_details" content with the title "test location details"
    #First we get a MISS
    Then the page should be dynamically cacheable
    And the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
    And the "file_list" cache tag should not be used
    #Visit again we get a HIT
    When I reload the page
    Then the page should be dynamically cached
