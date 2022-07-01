@api
Feature: Service Page Content type
  As a MassGov alpha content editor,
  I want to be able to add guide pages,
  so that I can inform people about organizations and services.

  Scenario: Verify that the service page content type has the correct fields
    Given I am logged in as a user with the "administrator" role
    Then "service_page" content has the correct fields

  Scenario: Verify that the category metatag exists and has the correct value.
    Given I am viewing a published "service_page" with the title "test service page"
    Then I should see a "category" meta tag of "services"

  @caching
  Scenario: Verify that the service page content type shows events immediately
    Given I am viewing a published "service_page" with the title "Events Service Page"
    Given I add events in the service section to "Events Service Page"
    Then I should not see "Upcoming Events"
    And an event "MyUpcomingEvent" referencing service_page "Events Service Page" happening at "now +1 day"
    When I reload the page
    Then I should see "MyUpcomingEvent"
    Then the "node_list" cache tag should not be used
    And the "paragraph_list" cache tag should not be used
